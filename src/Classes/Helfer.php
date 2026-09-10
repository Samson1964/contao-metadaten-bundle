<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Classes;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Kleine Helfer rund um den Contao-Behälter, die in 4.13 und 5.x gleich funktionieren.
 *
 * Beide Methoden gehen defensiv vor und liefern im Zweifel einen neutralen
 * Wert, damit sie auch außerhalb einer laufenden Contao-Anfrage — etwa im
 * Prüfstand mit einem Minimalbehälter — nicht abbrechen.
 */
final class Helfer
{
	/**
	 * Stellt fest, ob die laufende Anfrage eine Backend-Anfrage ist.
	 *
	 * Ersatz für die in Contao 5 entfallene Konstante TL_MODE. Ohne Behälter,
	 * ohne Anfrage oder ohne Scope-Matcher lautet die Antwort „nein“ — dann
	 * unterbleibt allenfalls das Laden des Backend-Stylesheets.
	 *
	 * @return bool true nur bei einer erkannten Backend-Anfrage
	 */
	public static function istBackend(): bool
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('request_stack') || !$container->has('contao.routing.scope_matcher'))
		{
			return false;
		}

		$stack = $container->get('request_stack');
		$matcher = $container->get('contao.routing.scope_matcher');

		if (!$stack instanceof RequestStack || !$matcher instanceof ScopeMatcher)
		{
			return false;
		}

		$request = $stack->getCurrentRequest();

		return null !== $request && $matcher->isBackendRequest($request);
	}

	/**
	 * Liefert den Wert des Anfrage-Tokens für eigene Backend-Formulare.
	 *
	 * Ersatz für die in Contao 5 entfallene Konstante REQUEST_TOKEN. Der
	 * ContaoCsrfTokenManager kennt in beiden Fassungen getDefaultTokenValue();
	 * steckt hinter dem Dienst nur die Symfony-Schnittstelle, wird der Token
	 * über seinen Namen aus der Konfiguration geholt.
	 *
	 * @return string Der Token, oder ein leerer Text ohne passenden Dienst
	 */
	public static function requestToken(): string
	{
		$container = System::getContainer();

		if (null === $container || !$container->has('contao.csrf.token_manager'))
		{
			return '';
		}

		$manager = $container->get('contao.csrf.token_manager');

		if (!$manager instanceof CsrfTokenManagerInterface)
		{
			return '';
		}

		if ($manager instanceof ContaoCsrfTokenManager)
		{
			return $manager->getDefaultTokenValue();
		}

		return $manager->getToken((string) Config::get('csrfTokenName'))->getValue();
	}
}
