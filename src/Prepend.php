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

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Process;

class Prepend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehaviors([
            'AntispamInitFilters' => static function (ArrayObject $spamfilters): void {
                $spamfilters->append(AntispamFilterAccessibleCaptcha::class);
            },
        ]);

        App::behavior()->addBehavior(
            'publicCommentFormAfterContent',
            FrontendBehaviors::publicCommentFormAfterContent(...)
        );

        App::behavior()->addBehavior('exportFull', BackendBehaviors::exportFull(...));
        App::behavior()->addBehavior('exportSingle', BackendBehaviors::exportSingle(...));

        return true;
    }
}
