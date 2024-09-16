<?php
/**
 * @brief accessibleCaptcha, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Julien Wajsberg and contributors
 *
 * @copyright Julien Wajsberg
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\accessibleCaptcha;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Network\Http;
use Exception;

class AccessibleCaptcha
{
    public const CAPTCHA_TABLE_NAME      = 'captcha';
    public const CAPTCHA_HASH_TABLE_NAME = 'captcha_hash';

    private string $table;
    private string $table_hash;

    // ttl des hash en minutes
    private static int $hash_ttl_min = 60; // 1h

    public function __construct()
    {
        $this->table      = App::con()->prefix() . self::CAPTCHA_TABLE_NAME;
        $this->table_hash = App::con()->prefix() . self::CAPTCHA_HASH_TABLE_NAME;
    }

    /**
     * Gets the random question and hash.
     *
     * Ce hash fait l'association entre cette question et le formulaire, et sera supprimé
     * lorsque la question sera répondue.
     *
     * @param      string  $blog_id  The blog identifier
     *
     * @return     array<string, mixed>   The random question and hash.
     */
    public function getRandomQuestionAndHash(string $blog_id): array
    {
        $question         = $this->getRandomQuestion($blog_id);
        $question['hash'] = $this->setAndReturnHashForQuestion((int) $question['id']);

        return $question;
    }

    /**
     * Determines if answer is correct for the given hash.
     *
     * si la réponse est correcte, on supprime le hash
     * cette méthode supprime aussi les hash obsolètes
     *
     * @param      string  $hash    The hash
     * @param      string  $answer  The answer
     *
     * @return     bool    True if answer correct for hash, False otherwise.
     */
    public function isAnswerCorrectForHash(string $hash, string $answer): bool
    {
        if ($this->checkAnswer($hash, $answer)) {
            $this->removeHash($hash);

            return true;
        }

        return false;
    }

    /**
     * Gets the question for hash.
     *
     * @param      string  $hash   The hash
     *
     * @return     array<string, mixed>   The question for hash.
     */
    public function getQuestionForHash(string $hash): array
    {
        $sql = new SelectStatement();
        $sql
            ->from([
                $sql->as($this->table, 'C'),
                $sql->as($this->table_hash, 'H'),
            ])
            ->columns([
                $sql->as('C.id', 'id'),
                $sql->as('C.question', 'question')])
            ->where('H.hash = ' . $sql->quote($hash))
            ->and('H.captcha_id = C.id');

        $question = $sql->select() ?? MetaRecord::newFromArray([]);

        return [
            'id'       => (int) $question->id,
            'question' => $question->question,
            'hash'     => $hash,
        ];
    }

    /**
     * Gets the random question.
     * Cette méthode initialise une question pour ce blog s'il n'en existe pas encore
     *
     * @param      string  $blog_id  The blog identifier
     *
     * @return     array<string, mixed>   The random question.
     */
    private function getRandomQuestion(string $blog_id): array
    {
        $this->checkAndInitQuestions($blog_id);

        // On tire une question au hasard
        $rand = rand(0, $this->getCountQuestions($blog_id) - 1);

        // On récupére son contenu
        return $this->getQuestionInOrder($blog_id, $rand);
    }

    /**
     * Gets the question in order.
     *
     * @param      string  $blog_id  The blog identifier
     * @param      int     $nb       The number of
     *
     * @return     array<string, mixed>   The question in order.
     */
    private function getQuestionInOrder(string $blog_id, int $nb): array
    {
        $sql = new SelectStatement();
        $sql
            ->columns([
                'id',
                'question',
            ])
            ->from($this->table)
            ->where('blog_id = ' . $sql->quote($blog_id))
            ->order('id ASC')
            ->limit([$nb, 1])
        ;

        $rs = $sql->select();

        return $rs ? [
            'id'       => $rs->id,
            'question' => $rs->question,
        ] : []; // May be we will have to cope with this case in the future?
    }

    /**
     * Check if the answer is correct
     *
     * @param      string  $hash    The hash
     * @param      string  $answer  The answer
     *
     * @return     bool
     */
    private function checkAnswer(string $hash, string $answer): bool
    {
        if ($hash === '' || $answer === '') {
            return false;
        }

        // Vérifions que la réponse est correcte
        $sql = new SelectStatement();
        $sql
            ->from([
                $sql->as($this->table, 'C'),
                $sql->as($this->table_hash, 'H'),
            ])
            ->column($sql->count('C.id'))
            ->where('H.hash = ' . $sql->quote($hash))
            ->and('H.captcha_id = C.id')
            ->and('C.answer = ' . $sql->quote($answer))
        ;

        $rs = $sql->select();
        if ($rs) {
            return (int) $rs->f(0) > 0;
        }

        return false;
    }

    /**
     * Removes a hash.
     *
     * @param      string  $hash   The hash
     */
    private function removeHash(string $hash): void
    {
        if ($hash === '') {
            return;
        }

        // On en profite pour enlever les anciens
        $expired_timestamp = gmmktime((int) gmdate('H'), (int) gmdate('i') - self::$hash_ttl_min);
        $expired_datetime  = gmdate('Y-m-d H:i:s', $expired_timestamp === false ? null : $expired_timestamp);

        $sql = new DeleteStatement();
        $sql
            ->from($this->table_hash)
            ->where('hash = ' . $sql->quote($hash))
            ->or('timestamp < ' . $sql->quote($expired_datetime))
            ->delete()
        ;

        App::blog()->triggerBlog();
    }

    /**
     * Sets and return hash for question.
     *
     * @param      int     $id     The new value
     *
     * @return     string
     */
    private function setAndReturnHashForQuestion(int $id): string
    {
        App::con()->writeLock($this->table_hash);

        try {
            // Get a new id
            $sql = new SelectStatement();
            $sql
                ->column($sql->max('id'))
                ->from($this->table_hash)
            ;

            $rs     = $sql->select();
            $new_id = $rs ? (int) $rs->f(0) + 1 : 0;

            $hash            = $this->getHash();
            $cur             = App::con()->openCursor($this->table_hash);
            $cur->captcha_id = $id;
            $cur->id         = $new_id;
            $cur->timestamp  = gmdate('Y-m-d H:i:s');
            $cur->hash       = $hash;

            $cur->insert();

            App::con()->unlock();
        } catch (Exception $e) {
            App::con()->unlock();

            throw $e;
        }

        return $hash;
    }

    /**
     * Gets the hash.
     *
     * @return     string  The hash.
     */
    private function getHash(): string
    {
        // on va supposer que c'est suffisamment random pour un captcha
        return Http::browserUID(Crypt::hmac(App::config()->masterKey(), Crypt::createPassword()));
    }

    /**
     * Gets the questions count.
     *
     * @param      string  $blog_id  The blog identifier
     *
     * @return     int     The count.
     */
    private function getCountQuestions(string $blog_id): int
    {
        $sql = new SelectStatement();
        $sql
            ->column($sql->count('id'))
            ->from($this->table)
            ->where('blog_id = ' . $sql->quote($blog_id))
        ;
        $rs = $sql->select();

        return $rs ? (int) $rs->f(0) : 0;
    }

    /**
     * Check and init questions
     *
     * @param      string  $blog_id  The blog identifier
     */
    private function checkAndInitQuestions(string $blog_id): void
    {
        App::con()->writeLock($this->table);

        try {
            $count = $this->getCountQuestions($blog_id);
            if ($count === 0) {
                $this->initQuestions($blog_id);
            }
            App::con()->unlock();
        } catch (Exception $e) {
            App::con()->unlock();

            throw $e;
        }
    }

    /**
     * Initializes the questions.
     *
     * @param      string  $blog_id  The blog identifier
     */
    public function initQuestions(string $blog_id): void
    {
        // On supprime tout
        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('blog_id = ' . $sql->quote($blog_id))
            ->delete()
        ;

        // Et on ajoute la question par défaut
        $this->addQuestion(
            $blog_id,
            __('What makes two plus two?'),
            '4'
        );
    }

    /**
     * Adds a question.
     *
     * @param      string  $blog_id   The blog identifier
     * @param      string  $question  The question
     * @param      string  $answer    The answer
     */
    public function addQuestion(string $blog_id, string $question, string $answer): void
    {
        // Get a new id
        $sql = new SelectStatement();
        $sql
            ->column($sql->max('id'))
            ->from($this->table)
        ;

        $rs = $sql->select();
        $id = $rs ? (int) $rs->f(0) + 1 : 0;

        // Insert the new question
        $cur           = App::con()->openCursor($this->table);
        $cur->id       = $id;
        $cur->question = $question;
        $cur->answer   = $answer;
        $cur->blog_id  = $blog_id;
        $cur->insert();
    }

    /**
     * Gets all questions.
     *
     * @param      string  $blog_id  The blog identifier
     *
     * @return     array<int, array<string, mixed>>   All questions.
     */
    public function getAllQuestions(string $blog_id): array
    {
        $sql = new SelectStatement();
        $sql
            ->columns([
                'id',
                'question',
                'answer',
            ])
            ->from($this->table)
            ->where('blog_id = ' . $sql->quote($blog_id))
        ;

        $rs     = $sql->select();
        $result = [];
        if ($rs) {
            while ($rs->fetch()) {
                $result[] = [
                    'id'       => $rs->id,
                    'question' => $rs->question,
                    'answer'   => $rs->answer,
                ];
            }
        }

        return $result;
    }

    /**
     * Removes questions.
     *
     * @param      string       $blog_id  The blog identifier
     * @param      array<int>   $arr_ids  The arr identifiers
     */
    public function removeQuestions(string $blog_id, array $arr_ids): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('blog_id = ' . $sql->quote($blog_id))
            ->and('id ' . $sql->in($arr_ids, 'int'))
            ->delete()
        ;
    }
}
