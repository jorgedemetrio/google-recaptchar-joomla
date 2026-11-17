<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Captcha.ReCaptcha
 *
 * @copyright   (C) 2026 Turismo. Baseado no plugin reCAPTCHA do Joomla! Project.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Captcha\ReCaptcha\Extension;

use Joomla\CMS\Event\Captcha\CaptchaSetupEvent;
use Joomla\CMS\Event\Privacy\CollectCapabilitiesEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Captcha\ReCaptcha\Provider\ReCaptchaProvider;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Google reCAPTCHA para o Joomla 6 (checkbox v2, invisivel v2 e v3), no padrao de provedores
 * de captcha introduzido no Joomla 5 (evento onCaptchaSetup) — sem a API legada onInit/onDisplay.
 */
final class ReCaptcha extends CMSPlugin implements SubscriberInterface
{
    /**
     * Carrega o arquivo de idioma ao instanciar.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onCaptchaSetup'                    => 'setupCaptcha',
            'onPrivacyCollectAdminCapabilities' => 'collectPrivacyCapabilities',
        ];
    }

    /**
     * Registra o provedor "recaptcha" (o nome e o elemento do plugin, usado na Configuracao Global e nos formularios).
     *
     * @param   CaptchaSetupEvent  $event  Evento de registro.
     *
     * @return  void
     */
    public function setupCaptcha(CaptchaSetupEvent $event): void
    {
        $event->getCaptchaRegistry()->add(new ReCaptchaProvider($this->params, $this->getApplication()));
    }

    /**
     * Informa ao Joomla (Privacidade > Recursos) que o IP do visitante e enviado ao Google na verificacao.
     *
     * @param   CollectCapabilitiesEvent  $event  Evento de coleta.
     *
     * @return  void
     */
    public function collectPrivacyCapabilities(CollectCapabilitiesEvent $event): void
    {
        $lang = $this->getApplication()->getLanguage();
        $event->addResult([
            $lang->_('PLG_CAPTCHA_RECAPTCHA') => [$lang->_('PLG_RECAPTCHA_PRIVACY_CAPABILITY_IP_ADDRESS')],
        ]);
    }
}
