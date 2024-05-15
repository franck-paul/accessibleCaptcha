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
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\importExport\FlatExport;

class BackendBehaviors
{
    public static function exportFull(FlatExport $exp): string
    {
        $exp->exportTable(AccessibleCaptcha::$table);

        return '';
    }

    public static function exportSingle(FlatExport $exp, string $blog_id): string
    {
        $sql = new SelectStatement();
        $sql
            ->column('*')
            ->from(App::con()->prefix() . AccessibleCaptcha::$table)
            ->where('blog_id = ' . $sql->quote($blog_id))
        ;

        $exp->export(
            AccessibleCaptcha::$table,
            $sql->statement()
        );

        return '';
    }
}
