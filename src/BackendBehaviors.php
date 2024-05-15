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

class BackendBehaviors
{
    public static function exportFull($core, $exp)
    {
        $exp->exportTable(AccessibleCaptcha::$table);
    }

    public static function exportSingle($core, $exp, $blog_id)
    {
        $exp->export(
            AccessibleCaptcha::$table,
            'SELECT * ' .
            'FROM ' . $core->prefix . AccessibleCaptcha::$table . ' ' .
            "WHERE blog_id = '{$blog_id}'"
        );
    }
}
