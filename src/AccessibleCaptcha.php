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

use Dotclear\Helper\Crypt;
use Dotclear\Helper\Network\Http;
use Exception;

class AccessibleCaptcha
{
    // nom des tables
    public static $table       = 'captcha';
    private static $table_hash = 'captcha_hash';

    // ttl des hash en minutes
    // devrait être en settings ?
    private static $hash_ttl_min = 60; // 1h

    // ----- méthodes publiques -----

    /**
     * retourne une question au pif et crée un hash dans la base de données.
     * Ce hash fait l'association entre cette question et le formulaire, et sera supprimé
     * lorsque la question sera répondue.
     * @param $blog_id l'id du blog
     * @return le hash créé et inséré dans la base
     */
    public function getRandomQuestionAndHash($blog_id)
    {
        $question         = $this->getRandomQuestion($blog_id);
        $question['hash'] = $this->setAndReturnHashForQuestion($question['id']);

        return $question;
    }

    /**
     * vérifie une réponse par rapport à un hash
     * si la réponse est correcte, on supprime le hash
     * cette méthode supprime aussi les hash obsolètes
     * @param $hash le hash correspondant à la question à vérifier
     * @param $answer la réponse à vérifier
     * @return true si la réponse est juste
     */
    public function isAnswerCorrectForHash($hash, $answer)
    {
        if ($this->checkAnswer($hash, $answer)) {
            $this->removeHash($hash);

            return true;
        }

        // si non
        return false;
    }

    /**
     * Retourne la question associée à un hash en particulier
     * @param $hash le hash correspondant à la question à récupérer
     * @return la question associée au hash
     */
    public function getQuestionForHash($hash)
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

    // ----- fin des méthodes publiques -----

    // ----- méthodes privées -----

    /**
     * retourne une question au pif
     * cette méthode initialise une question pour ce blog s'il n'en existe pas encore
     * @return une question au pif
     */
    private function getRandomQuestion($blog_id)
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

    private function getQuestionInOrder($blog_id, $nb)
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

    private function checkAnswer($hash, $answer)
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

    private function removeHash($hash)
    {
        global $core;
        $con = & $core->con;

        $query = 'delete from ' . $core->prefix . self::$table_hash . " where hash = '" . $con->escape($hash) . "'";

        // et on en profite pour enlever les anciens
        $expired_timestamp = gmmktime((int) gmdate('H'), gmdate('i') - self::$hash_ttl_min);
        $expired_datetime  = gmdate('Y-m-d H:i:s', $expired_timestamp);
        $query .= " or timestamp < '" . $con->escape($expired_datetime) . "'";
        $con->execute($query);
    }

    /**
     * retourne une question en particulier
     * @param $id id de la question à retourner
     * @ return Question qui à l'$id passé en paramètre
     */
    private function getQuestion($id)
    {
        global $core;
        $con = & $core->con;

        $query    = 'select question, answer from ' . $core->prefix . self::$table . " where id = '" . $con->escape($id) . "'";
        $question = $con->select($query);

        return [
            'id'       => $id,
            'question' => $question->question,
            'answer'   => $question->answer,
        ];
    }

    private function setAndReturnHashForQuestion($id)
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

    // on va supposer que c'est suffisamment random pour un captcha
    private function getHash()
    {
        $key = Http::browserUID(Crypt::hmac(DC_MASTER_KEY, Crypt::createPassword()));

        return $key;
    }

    private function getCountQuestions($blog_id)
    {
        global $core;
        $con = & $core->con;

        $query = 'select count(id) from ' . $core->prefix . self::$table . " where blog_id = '" . $con->escape($blog_id) . "'";
        $count = $con->select($query)->f(0);

        return $count;
    }

    private function checkAndInitQuestions($blog_id)
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

    public function initQuestions($blog_id)
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
            __('4')
        );
    }

    public function addQuestion($blog_id, $question, $answer, $id = -1)
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

    public function getAllQuestions($blog_id)
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

    public function removeQuestions($blog_id, $arr_ids)
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
