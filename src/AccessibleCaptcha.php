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
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Network\Http;
use Exception;

class AccessibleCaptcha
{
    // nom des tables
    public static string $table       = 'captcha';
    private static string $table_hash = 'captcha_hash';

    // ttl des hash en minutes
    private static int $hash_ttl_min = 60; // 1h

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

        // si non
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
        global $core;
        $con = & $core->con;

        $query = 'select c.id as id, c.question as question '
              . 'from ' . $core->prefix . self::$table . ' as c, ' . $core->prefix . self::$table_hash . ' as ch '
              . " where ch.hash = '" . $con->escape($hash) . "' and ch.captcha_id = c.id ";

        $question = $con->select($query);

        return [
            'id'       => $question->id,
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
        // on récupère le nombre de questions
        $count = $this->getCountQuestions($blog_id);
        // on demande la nieme
        $rand = rand(0, $count - 1);
        // on va récupérer la nieme
        $question = $this->getQuestionInOrder($blog_id, $rand);

        return $question;
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
        global $core;
        $con = & $core->con;

        $query = 'select id, question from ' . $core->prefix . self::$table .
          " where blog_id = '" . $con->escape($blog_id) . "' order by id asc "
          . $con->limit($nb, 1);

        $question = $con->select($query);

        return [
            'id'       => $question->id,
            'question' => $question->question,
        ];
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
        global $core;
        $con = & $core->con;

        // vérifions que la réponse est correcte
        $query = 'select count(c.id) from ' . $core->prefix . self::$table . ' as c, ' . $core->prefix . self::$table_hash . ' as ch '
              . " where ch.hash = '" . $con->escape($hash) . "' and ch.captcha_id = c.id "
              . " and c.answer = '" . $con->escape($answer) . "'";
        $count = $con->select($query)->f(0);

        return ($count > 0);
    }

    /**
     * Removes a hash.
     *
     * @param      string  $hash   The hash
     */
    private function removeHash(string $hash): void
    {
        global $core;
        $con = & $core->con;

        $query = 'delete from ' . $core->prefix . self::$table_hash . " where hash = '" . $con->escape($hash) . "'";

        // et on en profite pour enlever les anciens
        $expired_timestamp = gmmktime((int) gmdate('H'), (int) gmdate('i') - self::$hash_ttl_min);
        $expired_datetime  = gmdate('Y-m-d H:i:s', $expired_timestamp === false ? null : $expired_timestamp);
        $query .= " or timestamp < '" . $con->escape($expired_datetime) . "'";
        $con->execute($query);
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
        global $core;
        $con = & $core->con;

        $con->writeLock($core->prefix . self::$table_hash);

        try {
            $new_id = $con->select(
                'SELECT MAX(id) ' .
                'FROM ' . $core->prefix . self::$table_hash
            )->f(0) + 1;

            $hash            = $this->getHash();
            $cur             = $con->openCursor($core->prefix . self::$table_hash);
            $cur->captcha_id = $id;
            $cur->id         = $new_id;
            $cur->timestamp  = gmdate('Y-m-d H:i:s');
            $cur->hash       = $hash;

            $cur->insert();

            $con->unlock();
        } catch (Exception $e) {
            $con->unlock();

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
        global $core;
        $con = & $core->con;

        $query = 'select count(id) from ' . $core->prefix . self::$table . " where blog_id = '" . $con->escape($blog_id) . "'";

        return (int) $con->select($query)->f(0);
    }

    /**
     * Check and init questions
     *
     * @param      string  $blog_id  The blog identifier
     */
    private function checkAndInitQuestions(string $blog_id): void
    {
        global $core;
        $con = & $core->con;

        $con->writeLock($core->prefix . self::$table);

        try {
            $count = $this->getCountQuestions($blog_id);
            if ($count == 0) {
                $this->initQuestions($blog_id);
            }
            $con->unlock();
        } catch (Exception $e) {
            $con->unlock();

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
        global $core;
        $con = & $core->con;

        // on supprime tout
        $delete_query = 'delete from ' . $core->prefix . self::$table . " where blog_id = '" . $con->escape($blog_id) . "'";
        $con->execute($delete_query);

        // et on ajoute la question par défaut
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
        global $core;
        $con = & $core->con;

        // calculate new id
        $new_id = $con->select(
            'SELECT MAX(id) ' .
            'FROM ' . $core->prefix . self::$table
        )->f(0);

        if (is_numeric($new_id)) {
            $new_id++;
        } else {
            // no id yet
            $new_id = 0;
        }

        $cur           = $con->openCursor($core->prefix . self::$table);
        $cur->id       = $new_id;
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
        global $core;
        $con = & $core->con;

        $query = 'select id, question, answer from ' . $core->prefix . self::$table . " where blog_id = '" . $con->escape($blog_id) . "'";
        $rs    = $con->select($query);

        $result = [];
        while ($rs->fetch()) {
            $result[] = [
                'id'       => $rs->id,
                'question' => $rs->question,
                'answer'   => $rs->answer,
            ];
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
        global $core;
        $con = & $core->con;

        $delete_query = 'delete from ' . $core->prefix . self::$table . " where blog_id = '" . $con->escape($blog_id) . "'" .
            ' and (false';
        foreach ($arr_ids as $id) {
            $delete_query .= " or id = '" . $con->escape($id) . "'";
        }
        $delete_query .= ')';

        $con->execute($delete_query);
    }
}
