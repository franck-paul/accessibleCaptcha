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

use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\antispam\SpamFilter;
use form;

class AntispamFilterAccessibleCaptcha extends SpamFilter
{
    /** @var string Filter name */
    public string $name = 'Accessible Captcha';

    /** @var bool Filter has settings GUI? */
    public bool $has_gui = true;

    private string $style_p      = 'margin: .2em 0; padding: 0 0.5em; ';
    private string $style_answer = 'margin: 0 0 0 .5em; ';

    /**
     * Sets the filter description.
     */
    protected function setInfo(): void
    {
        $this->description = __('This is an accessible captcha.');
    }

    /**
     * This method should return if a comment is a spam or not. If it returns true
     * or false, execution of next filters will be stoped. If should return nothing
     * to let next filters apply.
     *
     * Your filter should also fill $status variable with its own information if
     * comment is a spam.
     *
     * @param      string  $type     The comment type (comment / trackback)
     * @param      string  $author   The comment author
     * @param      string  $email    The comment author email
     * @param      string  $site     The comment author site
     * @param      string  $ip       The comment author IP
     * @param      string  $content  The comment content
     * @param      int     $post_id  The comment post_id
     * @param      string  $status   The comment status
     */
    public function isSpam($type, $author, $email, $site, $ip, $content, $post_id, &$status)
    {
        $accessibleCaptcha = new AccessibleCaptcha();

        $question_hash = $_POST['c_question_hash'];
        $answer        = $_POST['c_answer'];
        if (! $answer) {
            $status = 'Filtered';

            return true;
        }

        if (!$accessibleCaptcha->isAnswerCorrectForHash($question_hash, $answer)) {
            $status = 'Filtered';

            return true;
        }
    }

    /**
     * This method returns filter status message. You can overload this method to
     * return a custom message. Message is shown in comment details and in
     * comments list.
     *
     * @param      string  $status      The status
     * @param      int     $comment_id  The comment identifier
     *
     * @return     string  The status message.
     */
    public function getStatusMessage(string $status, ?int $comment_id): string
    {
        return sprintf(__('Filtered by %s.'), $this->guiLink());
    }

    /**
     * This method is called when you enter filter configuration. Your class should
     * have $has_gui property set to "true" to enable GUI.
     *
     * @param      string  $url    The GUI url
     *
     * @return     string  The GUI HTML content
     */
    public function gui(string $url): string
    {
        global $core;

        $accessibleCaptcha = new AccessibleCaptcha();

        // ajout de questions
        if (! (empty($_POST['c_question']) || empty($_POST['c_answer']))) {
            $accessibleCaptcha->addQuestion(
                $core->blog->id,
                $_POST['c_question'],
                $_POST['c_answer']
            );

            // redirection pour que l'user puisse faire "reload"
            Http::redirect($url . '&added=1');
        }

        // suppression de questions
        if (! empty($_POST['c_d_questions']) && is_array($_POST['c_d_questions'])) {
            $accessibleCaptcha->removeQuestions($core->blog->id, $_POST['c_d_questions']);
            Http::redirect($url . '&deleted=1');
        }

        // réinit
        if (! empty($_POST['c_createlist'])) {
            $accessibleCaptcha->initQuestions($core->blog->id);
            Http::redirect($url . '&reset=1');
        }

        // assez joué, maintenant on affiche
        $res = '';

        if (!empty($_GET['added'])) {
            $res .= '<p class="message">' . __('Question has been successfully added.') . '</p>';
        }
        if (!empty($_GET['deleted'])) {
            $res .= '<p class="message">' . __('Questions have been successfully removed.') . '</p>';
        }
        if (!empty($_GET['reset'])) {
            $res .= '<p class="message">' . __('Questions list has been successfully reinitialized.') . '</p>';
        }

        $res .= '<form action="' . Html::escapeURL($url) . '" method="post">' .
              '<fieldset><legend>' . __('Add a question') . '</legend>' .
              '<p><label>' . __('Question to add:') . ' ' .
              form::field('c_question', 40, 255) .
              '</label></p>' .
              '<p><label>' . __('Answer:') . ' ' .
              form::field('c_answer', 40, 255) .
              '</label></p>';
        $res .= $core->formNonce() .
          '<input type="submit" value="' . __('Add') . '"/></p>' .
          '</fieldset>' .
          '</form>';

        $allquestions = $accessibleCaptcha->getAllQuestions($core->blog->id);

        $res .= '<form action="' . html::escapeURL($url) . '" method="post">' .
          '<fieldset><legend>' . __('Question list') . '</legend>';

        foreach ($allquestions as $question) {
            $res .= '<p style="' . $this->style_p . '"><label class="classic">' .
                    form::checkbox('c_d_questions[]', $question['id']) .
                    ' <strong>' . __('Question:') . '</strong> ' .
                    html::escapeHTML($question['question']) .
                    ' <strong style="' . $this->style_answer . '">' . __('Answer:') . '</strong> ' .
                    html::escapeHTML($question['answer']) .
                    '</label></p>';
        }

        $res .= '<p>' . $core->formNonce() .
                '<input class="submit" type="submit" value="' . __('Delete selected questions') . '"/></p>';

        $res .= '</fieldset></form>';

        $res .= '<form action="' . html::escapeURL($url) . '" method="post">' .
            '<p><input type="submit" value="' . __('Reset the list') . '" />' .
            form::hidden(['c_createlist'], 1) .
            $core->formNonce() . '</p>' .
      '</form>';

        $disableText = __('To disable this plugin, you need to disable it %sfrom the plugins page%s.');
        $disableText = sprintf($disableText, '<a href="plugins.php">', '</a>');

        $res .= '<p>' . $disableText . '</p>';

        return $res;
    }
}
