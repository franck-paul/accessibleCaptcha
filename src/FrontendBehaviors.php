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

class FrontendBehaviors
{
    public static function publicCommentFormAfterContent($core, $_ctx)
    {
        $accessibleCaptcha = new AccessibleCaptcha();

        if (($hash = $_POST['c_question_hash'])) {
            $question = $accessibleCaptcha->getQuestionForHash($hash);
        } else {
            $question = $accessibleCaptcha->getRandomQuestionAndHash($core->blog->id);
        }

        $escaped_value    = htmlspecialchars((string) $_POST['c_answer'], ENT_QUOTES);
        $escaped_question = htmlspecialchars((string) $question['question'], ENT_QUOTES);
        $escaped_hash     = htmlspecialchars((string) $question['hash'], ENT_QUOTES);

        echo "<p class='field'><label for='c_answer'>{$escaped_question}</label>
        <input name='c_answer' id='c_answer' type='text' size='30' maxlength='255' value='{$escaped_value}' />
        <input name='c_question_hash' id='c_question_hash' type='hidden' value='{$escaped_hash}' />
        </p>";
    }
}
