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
use Dotclear\Core\Process;
use Dotclear\Database\Structure;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Init
            $s = new Structure(App::con(), App::con()->prefix());

            $s->captcha
                ->field('id', 'bigint', 0, false)
                ->field('question', 'varchar', 150, false)
                ->field('answer', 'varchar', 150, false)
                ->field('blog_id', 'varchar', 32, false)

                ->primary('pk_captcha', 'id')
                ->index('idx_captcha_blog_btree', 'btree', 'blog_id')
                ->reference('fk_captcha_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');

            $s->captcha_hash
                ->field('id', 'bigint', 0, false)
                ->field('hash', 'varchar', 150, false)
                ->field('captcha_id', 'bigint', 0, false)
                ->field('timestamp', 'timestamp', 0, false)

                ->primary('pk_captcha_hash', 'id')
                ->index('idx_captcha_hash_btree', 'btree', 'hash')
                ->reference('fk_captcha_hash_captcha', 'captcha_id', 'captcha', 'id', 'cascade', 'cascade');

            // schema sync
            $si = new Structure(App::con(), App::con()->prefix());
            $si->synchronize($s);
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }
}
