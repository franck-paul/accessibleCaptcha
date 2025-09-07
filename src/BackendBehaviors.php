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
use Dotclear\Core\Backend\Page;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\importExport\FlatExport;

class BackendBehaviors
{
    public static function adminPageHTMLHead(): string
    {
        $fragments = explode('\\', AntispamFilterAccessibleCaptcha::class);
        $name      = array_pop($fragments);
        // Check if filter is currently displayed (depending on backend vars set by antispam plugin)
        if (App::backend()->filter?->id && App::backend()->filter->id === $name) {
            echo
            Page::jsJson('accessible-captcha', [
                'confirm_delete' => __('Are you sure you want to delete the selected questions (%s)?'),
                'confirm_reset'  => __('Are you sure you want to delete all the questions (%s)?'),
                'at_least_one'   => __('At least one question must remain!'),
            ]) .
            My::jsLoad('admin.js');
        }

        return '';
    }

    public static function exportFull(FlatExport $exp): string
    {
        $exp->exportTable(AccessibleCaptcha::CAPTCHA_TABLE_NAME);

        return '';
    }

    public static function exportSingle(FlatExport $exp, string $blog_id): string
    {
        $sql = new SelectStatement();
        $sql
            ->column('*')
            ->from(App::db()->con()->prefix() . AccessibleCaptcha::CAPTCHA_TABLE_NAME)
            ->where('blog_id = ' . $sql->quote($blog_id))
        ;

        $exp->export(
            AccessibleCaptcha::CAPTCHA_TABLE_NAME,
            $sql->statement()
        );

        return '';
    }
}
