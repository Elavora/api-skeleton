<?php

declare(strict_types=1);

/*
 * Limpeza do projeto criado com `composer create-project elavora/api-skeleton`.
 *
 * Só do repositório Elavora/api-skeleton: remove os arquivos que não fazem parte da entrega,
 * limpa as referências a eles e, por fim, apaga a si mesmo.
 * Especificação: docs/manutencao-do-skeleton.md, "`tools/skeleton-post-create.php`".
 */

/** Arquivos e pastas só do skeleton, na ordem da documentação. */
const SKELETON_ONLY = [
    '.github/workflows/skeleton-release.yml',
    '.github/workflows/skeleton-sync-labels.yml',
    '.github/workflows/skeleton-tests.yml',
    'docs/MIGRACAO-BOOTSTRAP.md',
    'docs/manutencao-do-skeleton.md',
    'tests-skeleton',
    'tools/skeleton-post-create.php',
];

const SELF_PATH = 'tools/skeleton-post-create.php';

/** Limite de entradas visitadas ao remover uma pasta (protege contra árvores inesperadas). */
const MAX_TREE_ENTRIES = 100000;

final class SkeletonCleanupFailure extends RuntimeException {}

/**
 * @param resource $stream
 */
function skeletonWrite($stream, string $message): void
{
    fwrite($stream, $message . PHP_EOL);
}

/** Remove um arquivo, link ou pasta (com todo o conteúdo). Devolve false quando o caminho não existe. */
function skeletonRemovePath(string $path): bool
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new SkeletonCleanupFailure("não foi possível remover {$path}");
        }

        return true;
    }
    if (!is_dir($path)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    $visited = 0;
    foreach ($iterator as $entry) {
        if (++$visited > MAX_TREE_ENTRIES) {
            throw new SkeletonCleanupFailure("{$path} tem mais de " . MAX_TREE_ENTRIES . ' entradas');
        }
        /** @var SplFileInfo $entry */
        $entryPath = $entry->getPathname();
        $removed = ($entry->isDir() && !$entry->isLink()) ? rmdir($entryPath) : unlink($entryPath);
        if (!$removed) {
            throw new SkeletonCleanupFailure("não foi possível remover {$entryPath}");
        }
    }
    if (!rmdir($path)) {
        throw new SkeletonCleanupFailure("não foi possível remover {$path}");
    }

    return true;
}

function skeletonWriteFile(string $path, string $content): void
{
    if (file_put_contents($path, $content) !== strlen($content)) {
        throw new SkeletonCleanupFailure("não foi possível gravar {$path}");
    }
}

/**
 * Remove o trecho entre a linha $start e a linha $end, inclusive. As outras linhas ficam iguais.
 *
 * @return string|null conteúdo novo, ou null quando o arquivo deve ficar igual
 * @throws InvalidArgumentException quando os marcadores estão incompletos ou fora de ordem
 */
function skeletonWithoutBlock(string $content, string $start, string $end): ?string
{
    $lines = preg_split('~(?<=\n)~', $content);
    if ($lines === false) {
        throw new InvalidArgumentException('não foi possível ler as linhas');
    }

    $starts = [];
    $ends = [];
    foreach ($lines as $index => $line) {
        $trimmed = trim($line);
        if ($trimmed === $start) {
            $starts[] = $index;
        } elseif ($trimmed === $end) {
            $ends[] = $index;
        }
    }

    if ($starts === [] && $ends === []) {
        return null;
    }
    if (count($starts) !== 1 || count($ends) !== 1 || $starts[0] > $ends[0]) {
        throw new InvalidArgumentException('marcadores incompletos ou fora de ordem');
    }

    return implode('', array_slice($lines, 0, $starts[0])) . implode('', array_slice($lines, $ends[0] + 1));
}

/**
 * @param resource $stdout
 * @param resource $stderr
 */
function skeletonCleanMarkedFile(string $root, string $relative, string $start, string $end, $stdout, $stderr): void
{
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        return;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        throw new SkeletonCleanupFailure("não foi possível ler {$path}");
    }

    try {
        $cleaned = skeletonWithoutBlock($content, $start, $end);
    } catch (InvalidArgumentException $exception) {
        skeletonWrite($stderr, "aviso: {$relative} ficou igual: " . $exception->getMessage());

        return;
    }
    if ($cleaned === null) {
        return;
    }

    skeletonWriteFile($path, $cleaned);
    skeletonWrite($stdout, "alterado: {$relative}");
}

function skeletonReadComposer(string $path): stdClass
{
    if (!is_file($path)) {
        throw new SkeletonCleanupFailure("{$path} não existe");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        throw new SkeletonCleanupFailure("não foi possível ler {$path}");
    }
    try {
        $composer = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new SkeletonCleanupFailure("{$path} não é um JSON válido: " . $exception->getMessage());
    }
    if (!$composer instanceof stdClass) {
        throw new SkeletonCleanupFailure("{$path} não é um objeto JSON");
    }

    return $composer;
}

/**
 * @param resource $stdout
 */
function skeletonCleanComposer(string $root, stdClass $composer, $stdout): void
{
    $scripts = $composer->scripts ?? null;
    if (!$scripts instanceof stdClass || !property_exists($scripts, 'post-create-project-cmd')) {
        return;
    }

    unset($scripts->{'post-create-project-cmd'});
    if (get_object_vars($scripts) === []) {
        unset($composer->scripts);
    }

    $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new SkeletonCleanupFailure('não foi possível gerar o composer.json: ' . json_last_error_msg());
    }
    skeletonWriteFile($root . '/composer.json', $json . "\n");
    skeletonWrite($stdout, 'alterado: composer.json');
}

/**
 * @param resource $stdout
 * @param resource $stderr
 */
function skeletonCleanup(string $root, string $self, $stdout, $stderr): int
{
    try {
        // Valida o composer.json antes de mexer em qualquer arquivo.
        $composer = skeletonReadComposer($root . '/composer.json');

        foreach (SKELETON_ONLY as $relative) {
            if ($relative === SELF_PATH) {
                continue;
            }
            if (skeletonRemovePath($root . '/' . $relative)) {
                skeletonWrite($stdout, "removido: {$relative}");
            }
        }

        skeletonCleanMarkedFile($root, 'README.md', '<!-- skeleton:inicio -->', '<!-- skeleton:fim -->', $stdout, $stderr);
        skeletonCleanMarkedFile($root, '.gitattributes', '# skeleton:inicio', '# skeleton:fim', $stdout, $stderr);
        skeletonCleanComposer($root, $composer, $stdout);

        if (skeletonRemovePath($self)) {
            skeletonWrite($stdout, 'removido: ' . SELF_PATH);
        }
        $tools = dirname($self);
        $remaining = scandir($tools);
        if ($remaining === false) {
            throw new SkeletonCleanupFailure("não foi possível ler {$tools}");
        }
        if (array_diff($remaining, ['.', '..']) === []) {
            if (!rmdir($tools)) {
                throw new SkeletonCleanupFailure("não foi possível remover {$tools}");
            }
            skeletonWrite($stdout, 'removido: tools');
        }
    } catch (SkeletonCleanupFailure $failure) {
        skeletonWrite($stderr, 'erro: ' . $failure->getMessage());

        return 1;
    }

    return 0;
}

if (PHP_SAPI !== 'cli') {
    exit(1);
}

exit(skeletonCleanup(dirname(__DIR__), __FILE__, STDOUT, STDERR));
