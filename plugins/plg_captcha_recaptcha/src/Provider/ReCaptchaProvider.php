<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Captcha.ReCaptcha
 *
 * @copyright   (C) 2026 Turismo. Baseado no plugin reCAPTCHA do Joomla! Project.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Captcha\ReCaptcha\Provider;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Captcha\CaptchaProviderInterface;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;
use Joomla\Utilities\IpHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provedor Google reCAPTCHA: checkbox v2, invisivel v2 e v3 (por pontuacao).
 */
final class ReCaptchaProvider implements CaptchaProviderInterface
{
    public const MODE_CHECKBOX  = 'checkbox';
    public const MODE_INVISIBLE = 'invisible';
    public const MODE_V3        = 'v3';

    /** Acao enviada ao Google no modo v3 (conferida na resposta). */
    public const V3_ACTION = 'joomla_form';

    /** Tempo maximo (s) da chamada de verificacao ao Google. */
    private const VERIFY_TIMEOUT = 8;

    /** Limite padrao do score do v3 (0.0 = bot, 1.0 = humano). */
    private const DEFAULT_THRESHOLD = 0.5;

    /**
     * Respostas ja verificadas na requisicao (o token do Google e de uso unico).
     *
     * @var array<string, bool>
     */
    private static array $verificados = [];

    public function __construct(
        private readonly Registry $params,
        private readonly ?CMSApplicationInterface $application
    ) {
    }

    public function getName(): string
    {
        return 'recaptcha';
    }

    /**
     * Modo configurado, sempre um dos tres validos.
     */
    private function modo(): string
    {
        $modo = (string) $this->params->get('mode', self::MODE_CHECKBOX);

        return \in_array($modo, [self::MODE_CHECKBOX, self::MODE_INVISIBLE, self::MODE_V3], true) ? $modo : self::MODE_CHECKBOX;
    }

    /**
     * Dominio do Google: google.com (padrao) ou recaptcha.net (para paises onde google.com e bloqueado).
     */
    private function dominio(): string
    {
        return ((int) $this->params->get('use_recaptchanet', 0) === 1) ? 'www.recaptcha.net' : 'www.google.com';
    }

    /**
     * HTML do desafio + carga dos assets (script do plugin e api.js do Google, uma vez por pagina).
     *
     * @param   string  $name        Nome do campo.
     * @param   array   $attributes  Atributos (id, class) vindos do CaptchaField.
     *
     * @return  string
     *
     * @throws  \RuntimeException  Sem chave publica configurada.
     */
    public function display(string $name = '', array $attributes = []): string
    {
        $app = $this->application;

        if (!$app instanceof CMSWebApplicationInterface) {
            return '';
        }

        $siteKey = trim((string) $this->params->get('public_key', ''));

        if ($siteKey === '') {
            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_NO_PUBLIC_KEY'));
        }

        $modo = $this->modo();
        $doc  = $app->getDocument();
        $wa   = $doc->getWebAssetManager();

        $doc->addScriptOptions('plg_captcha_recaptcha', [
            'mode'    => $modo,
            'sitekey' => $siteKey,
            'action'  => self::V3_ACTION,
        ]);

        $idioma = $app->getLanguage()->getTag();
        $api    = 'https://' . $this->dominio() . '/recaptcha/api.js?hl=' . rawurlencode($idioma);
        $api   .= ($modo === self::MODE_V3)
            ? '&render=' . rawurlencode($siteKey) . '&onload=turismoRecaptchaInit'
            : '&render=explicit&onload=turismoRecaptchaInit';

        // Plugins nao tem o joomla.asset.json carregado sozinho (so componentes/templates): registra o do plugin.
        $wa->getRegistry()->addExtensionRegistryFile('plg_captcha_recaptcha');

        // O script do plugin define o callback onload, entao precisa vir ANTES da api.js (defer preserva a ordem).
        $wa->useScript('plg_captcha_recaptcha.main')
            ->registerAndUseScript('plg_captcha_recaptcha.api', $api, [], ['defer' => true], ['plg_captcha_recaptcha.main'])
            ->useStyle('plg_captcha_recaptcha.main');

        $id    = htmlspecialchars((string) ($attributes['id'] ?? 'recaptcha_1'), ENT_QUOTES, 'UTF-8');
        $class = trim((string) ($attributes['class'] ?? ''));
        $class = htmlspecialchars(trim($class . ' g-recaptcha'), ENT_QUOTES, 'UTF-8');
        $key   = htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8');
        $aviso = '';

        if ($modo !== self::MODE_CHECKBOX && (int) $this->params->get('hide_badge_text', 1) === 1) {
            // Os Termos do Google exigem o aviso quando o selo e escondido.
            $aviso = '<small class="plg-recaptcha-legal">' . Text::_('PLG_RECAPTCHA_LEGAL_TEXT') . '</small>';
        }

        if ($modo === self::MODE_V3) {
            return '<div class="plg-recaptcha plg-recaptcha-v3"><input type="hidden" name="g-recaptcha-response" id="' . $id . '" class="g-recaptcha-v3" value="">'
                . $aviso . '</div>';
        }

        $atributos = ' data-sitekey="' . $key . '"';

        if ($modo === self::MODE_INVISIBLE) {
            $badge     = htmlspecialchars((string) $this->params->get('badge', 'bottomright'), ENT_QUOTES, 'UTF-8');
            $atributos .= ' data-size="invisible" data-badge="' . $badge . '"';
        } else {
            $tema      = (string) $this->params->get('theme', 'light') === 'dark' ? 'dark' : 'light';
            $tamanho   = (string) $this->params->get('size', 'normal') === 'compact' ? 'compact' : 'normal';
            $atributos .= ' data-theme="' . $tema . '" data-size="' . $tamanho . '" data-tabindex="' . (int) $this->params->get('tabindex', 0) . '"';
        }

        return '<div class="plg-recaptcha"><div id="' . $id . '" class="' . $class . '"' . $atributos . '></div>' . $aviso . '</div>';
    }

    /**
     * Verifica a resposta no Google (siteverify).
     *
     * @param   ?string  $code  Token do usuario; se vazio, le o campo g-recaptcha-response do POST.
     *
     * @return  bool  true quando valido.
     *
     * @throws  \RuntimeException  Falha de configuracao, resposta vazia/invalida ou Google inacessivel (mensagem exibida ao usuario).
     */
    public function checkAnswer(?string $code = null): bool
    {
        $app = $this->application;

        if (!$app instanceof CMSApplicationInterface) {
            return false;
        }

        $secret = trim((string) $this->params->get('private_key', ''));

        if ($secret === '') {
            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_NO_PRIVATE_KEY'), 500);
        }

        $resposta = trim((string) ($code ?: $app->getInput()->post->get('g-recaptcha-response', '', 'string')));

        if ($resposta === '') {
            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_EMPTY_SOLUTION'), 403);
        }

        if (isset(self::$verificados[$resposta])) {
            return self::$verificados[$resposta];
        }

        $dados = $this->consultarGoogle($secret, $resposta);
        $ok    = !empty($dados['success']);

        if ($ok && $this->modo() === self::MODE_V3) {
            $limite = (float) $this->params->get('threshold', self::DEFAULT_THRESHOLD);
            $score  = (float) ($dados['score'] ?? 0);
            $acao   = (string) ($dados['action'] ?? '');
            $ok     = $score >= $limite && $acao === self::V3_ACTION;

            if (!$ok) {
                Log::add(\sprintf('reCAPTCHA v3 reprovado: score=%.2f (minimo %.2f) action=%s', $score, $limite, $acao), Log::INFO, 'plg_captcha_recaptcha');
            }
        } elseif (!$ok) {
            $erros = \is_array($dados['error-codes'] ?? null) ? implode(',', array_map('strval', $dados['error-codes'])) : 'desconhecido';
            Log::add('reCAPTCHA reprovado: ' . $erros, Log::WARNING, 'plg_captcha_recaptcha');
        }

        self::$verificados[$resposta] = $ok;

        if (!$ok) {
            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_INVALID_SOLUTION'), 403);
        }

        return true;
    }

    /**
     * POST em /recaptcha/api/siteverify.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException  Google inacessivel ou resposta que nao e JSON.
     */
    private function consultarGoogle(string $secret, string $resposta): array
    {
        $post = ['secret' => $secret, 'response' => $resposta];
        $ip   = IpHelper::getIp();

        if ($ip !== '' && $ip !== null) {
            $post['remoteip'] = $ip;
        }

        try {
            $http = (new HttpFactory())->getHttp(new Registry(['userAgent' => 'Joomla-reCAPTCHA/1.0']));
            $r    = $http->post('https://' . $this->dominio() . '/recaptcha/api/siteverify', $post, [], self::VERIFY_TIMEOUT);
            $json = json_decode((string) $r->getBody(), true);
        } catch (\Throwable $e) {
            Log::add('reCAPTCHA: Google inacessivel: ' . $e->getMessage(), Log::ERROR, 'plg_captcha_recaptcha');

            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_UNREACHABLE'), 503);
        }

        if (!\is_array($json)) {
            Log::add('reCAPTCHA: resposta invalida do Google', Log::ERROR, 'plg_captcha_recaptcha');

            throw new \RuntimeException(Text::_('PLG_RECAPTCHA_ERROR_UNREACHABLE'), 503);
        }

        return $json;
    }

    public function setupField(FormField $field, \SimpleXMLElement $element): void
    {
        // Intencionalmente vazio: o reCAPTCHA não precisa configurar atributos extras no campo do formulário.
    }
}
