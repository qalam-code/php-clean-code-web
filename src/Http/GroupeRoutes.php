<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Http;

use InvalidArgumentException;

/**
 * Applique un préfixe, des options et des middleware communs à plusieurs routes.
 * Le résultat reste une liste ordinaire comprise par RouteurWeb.
 */
final class GroupeRoutes
{
    /**
     * Préfixe les chemins et applique les options communes sans écraser
     * celles déclarées explicitement sur une route. Les middleware du groupe
     * sont exécutés avant ceux propres à la route.
     *
     * @param string $prefixe Exemple : /admin
     * @param array<int,array<string,mixed>> $routes
     * @param array<string,mixed> $options Options communes : csrf et middleware
     * @return array<int,array<string,mixed>>
     */
    public static function avecPrefixe(string $prefixe, array $routes, array $options = []): array
    {
        $prefixe = '/' . trim($prefixe, '/');
        if ($prefixe === '/') {
            throw new InvalidArgumentException('Un groupe de routes doit avoir un prefixe non vide.');
        }

        foreach ($options as $nom => $valeur) {
            if ($nom === 'csrf' && is_bool($valeur)) {
                continue;
            }
            if ($nom === 'middleware' && is_array($valeur)) {
                foreach ($valeur as $middleware) {
                    if (!is_callable($middleware)) {
                        throw new InvalidArgumentException('Chaque middleware de groupe doit etre appelable.');
                    }
                }
                continue;
            }
            throw new InvalidArgumentException('Options de groupe invalides : seules csrf et middleware sont prises en charge.');
        }

        $routesGroupees = [];
        foreach ($routes as $route) {
            if (!is_array($route) || !isset($route['chemin']) || !is_string($route['chemin'])) {
                throw new InvalidArgumentException('Chaque route du groupe doit definir un chemin textuel.');
            }

            $cheminRelatif = trim($route['chemin'], '/');
            $route['chemin'] = $prefixe . ($cheminRelatif === '' ? '' : '/' . $cheminRelatif);

            if (array_key_exists('csrf', $options) && !array_key_exists('csrf', $route)) {
                // Une route peut toujours choisir explicitement sa propre politique CSRF.
                $route['csrf'] = $options['csrf'];
            }

            if (array_key_exists('middleware', $route) && !is_array($route['middleware'])) {
                throw new InvalidArgumentException('Les middleware de route doivent etre fournis dans un tableau.');
            }
            $middlewareRoute = isset($route['middleware']) ? $route['middleware'] : [];
            foreach ($middlewareRoute as $middleware) {
                if (!is_callable($middleware)) {
                    throw new InvalidArgumentException('Chaque middleware de route doit etre appelable.');
                }
            }
            if (array_key_exists('middleware', $options)) {
                // Les middleware du groupe forment la couche extérieure ; ceux de la route suivent.
                $route['middleware'] = array_merge($options['middleware'], $middlewareRoute);
            }

            $routesGroupees[] = $route;
        }

        return $routesGroupees;
    }
}
