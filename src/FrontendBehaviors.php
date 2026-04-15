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
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Html;

class FrontendBehaviors
{
    public static function publicHeadContent(): string
    {
        echo
        My::cssLoad('public.css');

        return '';
    }
    public static function publicCommentFormAfterContent(): string
    {
        // Post data helpers
        $_Str = fn (string $name, string $default = ''): string => isset($_POST[$name]) && is_string($val = $_POST[$name]) ? $val : $default;

        $accessibleCaptcha = new AccessibleCaptcha();

        $question_hash = $_Str('c_question_hash');
        if ($question_hash !== '') {
            $captcha = $accessibleCaptcha->getQuestionForHash($question_hash);
        } else {
            $captcha = $accessibleCaptcha->getRandomQuestionAndHash(App::blog()->id());
        }

        $answer = Html::escapeHTML($_Str('c_answer'));

        $question = Html::escapeHTML($captcha['question']);
        $hash     = Html::escapeHTML($captcha['hash']);

        if ($question === '' || $hash === '') {
            return '';
        }

        echo (new Para())
            ->class(['field', 'captcha-field'])
            ->items([
                (new Label($question))
                    ->for('c_answer'),
                (new Input('c_answer'))
                    ->size(30)
                    ->maxlength(255)
                    ->value($answer),
                (new Hidden('c_question_hash', $hash)),
            ])
        ->render();

        return '';
    }
}
