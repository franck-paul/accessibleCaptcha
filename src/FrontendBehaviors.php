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
        $accessibleCaptcha = new AccessibleCaptcha();

        /**
         * Captcha definition
         *
         * @var        array{question: string, hash: string}
         */
        $captcha = [];

        $question_hash = $_POST['c_question_hash'] ?? '';
        if ($question_hash !== '') {
            $captcha = $accessibleCaptcha->getQuestionForHash($question_hash);
        } else {
            $captcha = $accessibleCaptcha->getRandomQuestionAndHash(App::blog()->id());
        }

        if ($captcha === []) {
            return '';
        }

        $value = isset($_POST['c_answer']) ? Html::escapeHTML((string) $_POST['c_answer']) : '';

        $question = Html::escapeHTML((string) $captcha['question']);
        $hash     = Html::escapeHTML((string) $captcha['hash']);

        echo (new Para())
            ->class(['field', 'captcha-field'])
            ->items([
                (new Label($question))
                    ->for('c_answer'),
                (new Input('c_answer'))
                    ->size(30)
                    ->maxlength(255)
                    ->value($value),
                (new Hidden('c_question_hash', $hash)),
            ])
        ->render();

        return '';
    }
}
