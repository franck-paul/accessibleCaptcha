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
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\antispam\SpamFilter;

class AntispamFilterAccessibleCaptcha extends SpamFilter
{
    /** @var string Filter name */
    public string $name = 'Accessible Captcha';

    /** @var bool Filter has settings GUI? */
    public bool $has_gui = true;

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

        $question_hash = (string) $_POST['c_question_hash'];
        $answer        = $_POST['c_answer'] ?? '';

        if (!$answer) {
            return true;
        }

        if (!$accessibleCaptcha->isAnswerCorrectForHash($question_hash, $answer)) {
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
        $accessibleCaptcha = new AccessibleCaptcha();

        // Ajout de questions
        if (!(empty($_POST['c_question']) || empty($_POST['c_answer']))) {
            $accessibleCaptcha->addQuestion(
                App::blog()->id(),
                (string) $_POST['c_question'],
                (string) $_POST['c_answer']
            );

            Notices::addSuccessNotice(__('Question has been successfully added.'));
            Http::redirect($url);
        } else {
            if (!empty($_POST['c_addquestion'])) {
                Notices::addErrorNotice(__('Question and answer must be given.'));
            }
        }

        // Suppression de questions
        if (!empty($_POST['c_d_questions']) && is_array($_POST['c_d_questions'])) {
            $accessibleCaptcha->removeQuestions(App::blog()->id(), $_POST['c_d_questions']);

            Notices::addSuccessNotice(__('Questions have been successfully removed.'));
            Http::redirect($url);
        }

        // Réinit
        if (!empty($_POST['c_createlist'])) {
            $accessibleCaptcha->initQuestions(App::blog()->id());

            Notices::addSuccessNotice(__('Questions list has been successfully reinitialized.'));
            Http::redirect($url);
        }

        // Assez joué, maintenant on affiche

        // Formulaire d'ajout de question
        $res = (new Form('accessible-captcha-add'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Fieldset())
                    ->legend(new Legend(__('Add a question')))
                    ->fields([
                        (new Para())->items([
                            (new Input('c_question'))
                                ->size(80)
                                ->maxlength(255)
                                ->label((new Label(__('Question to add:'), Label::INSIDE_TEXT_BEFORE))),
                        ]),
                        (new Para())->items([
                            (new Input('c_answer'))
                                ->size(40)
                                ->maxlength(255)
                                ->label((new Label(__('Answer:'), Label::INSIDE_TEXT_BEFORE))),
                        ]),
                        (new Para())->items([
                            (new Submit('save', __('Add'))),
                            (new Hidden(['c_addquestion'], '1')),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
            ])
        ->render();

        $questions = $accessibleCaptcha->getAllQuestions(App::blog()->id());
        $items     = [];
        foreach ($questions as $question) {
            $items[] = (new Tr())->items([
                (new Td())->items([
                    (new Checkbox(['c_d_questions[]']))->value($question['id']),
                ]),
                (new Td())->class('maximal')->items([
                    (new Text(null, $question['question'])),
                ]),
                (new Td())->class('maximal')->items([
                    (new Text(null, $question['answer'])),
                ]),
            ]);
        }

        $res .= (new Form('accessible-captcha-list'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Fieldset())
                    ->legend(new Legend(__('Question list')))
                    ->fields([
                        (new Table())
                            ->thead(new Thead())
                            ->tbody(new Tbody())
                                ->items([
                                    (new Tr())->items([
                                        (new Th())->items([]),
                                        (new Th())->items([
                                            (new Text(null, __('Question:'))),
                                        ]),
                                        (new Th())->items([
                                            (new Text(null, __('Answer:'))),
                                        ]),
                                    ]),
                                    ...$items,
                                ]),
                        (new Div())
                            ->class(['two-cols', 'clearfix'])
                            ->items([
                                (new Para())->class(['col', 'checkboxes-helpers']),
                                (new Para())->class(['col', 'right', 'form-buttons'])->items([
                                    (new Submit('delete', __('Delete selected questions'))),
                                    App::nonce()->formNonce(),
                                ]),
                            ]),
                    ]),
            ])
        ->render();

        $res .= (new Form('accessible-captcha-reset'))
            ->action($url)
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Submit('reset', __('Reset the list'))),
                    (new Hidden(['c_createlist'], '1')),
                    App::nonce()->formNonce(),
                ]),
            ])
        ->render();

        return $res;
    }
}
