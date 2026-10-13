<?php

declare(strict_types=1);

namespace Slendie\Exceptions;

/**
 * Não existe `public/build/manifest.json`: falta correr `npm run build`.
 */
class ViteManifestNotFoundException extends ViteException
{
}
