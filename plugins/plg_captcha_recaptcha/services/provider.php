<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Captcha.ReCaptcha
 *
 * @copyright   (C) 2026 Turismo. Baseado no plugin reCAPTCHA do Joomla! Project.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Captcha\ReCaptcha\Extension\ReCaptcha;

return new class () implements ServiceProviderInterface {
    /**
     * Registra o plugin no container de injecao de dependencias.
     *
     * @param   Container  $container  Container DI.
     *
     * @return  void
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(ReCaptcha::class, function (Container $container) {
                $plugin = new ReCaptcha((array) PluginHelper::getPlugin('captcha', 'recaptcha'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
