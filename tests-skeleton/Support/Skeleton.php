<?php

declare(strict_types=1);

namespace SkeletonTests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Utilitários dos testes da entrega (docs/manutencao-do-skeleton.md).
 *
 * As checagens de referências conferem o FORMATO, nunca o valor, para que os PRs do
 * Dependabot (SHA, comentário de versão, restrição do Composer) não quebrem os testes.
 */
final class Skeleton
{
    public const DEV_IMAGE = 'ghcr.io/elavora/php-dev:8.3-latest';

    /** Tabela "Arquivos só do skeleton", na ordem da documentação. */
    public const SKELETON_ONLY = [
        '.github/workflows/skeleton-release.yml',
        '.github/workflows/skeleton-sync-labels.yml',
        '.github/workflows/skeleton-tests.yml',
        'docs/manutencao-do-skeleton.md',
        'tests-skeleton',
        'tools/skeleton-post-create.php',
    ];

    public const SCRIPT = 'tools/skeleton-post-create.php';

    public const README_START = '<!-- skeleton:inicio -->';
    public const README_END = '<!-- skeleton:fim -->';
    public const ATTRIBUTES_START = '# skeleton:inicio';
    public const ATTRIBUTES_END = '# skeleton:fim';

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function path(string $relative): string
    {
        return self::root() . '/' . $relative;
    }

    public static function read(string $relative): string
    {
        $path = self::path($relative);
        if (!is_file($path)) {
            throw new RuntimeException("Arquivo ausente: {$relative}");
        }

        return (string) file_get_contents($path);
    }

    // ---------------------------------------------------------------- formato das referências

    /** Linha `uses:` de um chamador do Elavora/.github: SHA de 40 hex minúsculos + ` # X.Y.Z`. */
    public static function isElavoraCallerLine(string $line, string $workflow): bool
    {
        $pattern = '~^\s*(?:-\s+)?uses:\s+Elavora/\.github/\.github/workflows/'
            . preg_quote($workflow, '~')
            . '@[0-9a-f]{40} # \d+\.\d+\.\d+\s*$~';

        return preg_match($pattern, $line) === 1;
    }

    /** Referência de action de terceiros: `dono/action@<SHA 40> # vN[.N[.N]]`. */
    public static function isPinnedActionRef(string $ref): bool
    {
        return preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+@[0-9a-f]{40} # v\d+(?:\.\d+){0,2}$~', trim($ref)) === 1;
    }

    /** Restrição do Composer com o major esperado (`^2.0` e `^2.1` passam; `^3.0` e `dev-*` falham). */
    public static function hasMajor(string $constraint, int $major): bool
    {
        return str_starts_with($constraint, '^' . $major . '.') && !str_contains($constraint, 'dev-');
    }

    /**
     * Referências à imagem de desenvolvimento encontradas no texto.
     *
     * @return list<string>
     */
    public static function imageReferences(string $text): array
    {
        preg_match_all('~(?<![\w./-])[\w./-]*php-dev(?::[\w.-]*\w)?~', $text, $matches);

        return $matches[0];
    }

    /** Referência válida em configuração: exatamente a tag que acompanha as estáveis. */
    public static function isValidImageReference(string $reference): bool
    {
        return $reference === self::DEV_IMAGE;
    }

    // ---------------------------------------------------------------- YAML (subconjunto, indentação de 2 espaços)

    /** @return list<string> valores de todas as linhas `uses:` */
    public static function usesValues(string $yaml): array
    {
        preg_match_all('~^\s*(?:-\s+)?uses:\s*(.+?)\s*$~m', $yaml, $matches);

        return $matches[1];
    }

    /** @return list<string> linhas `uses:` completas */
    public static function usesLines(string $yaml): array
    {
        preg_match_all('~^\s*(?:-\s+)?uses:.*$~m', $yaml, $matches);

        return array_map('rtrim', $matches[0]);
    }

    /** Tudo antes da linha `jobs:`. */
    public static function topLevel(string $yaml): string
    {
        $parts = preg_split('~^jobs:\s*$~m', $yaml, 2);

        return $parts[0] ?? '';
    }

    /** @return list<string> */
    public static function jobNames(string $yaml): array
    {
        $parts = preg_split('~^jobs:\s*$~m', $yaml, 2);
        if (!isset($parts[1])) {
            return [];
        }
        preg_match_all('~^  ([A-Za-z0-9_-]+):\s*$~m', $parts[1], $matches);

        return $matches[1];
    }

    public static function jobBlock(string $yaml, string $job): ?string
    {
        $parts = preg_split('~^jobs:\s*$~m', $yaml, 2);
        if (!isset($parts[1])) {
            return null;
        }
        $lines = preg_split('~\R~', $parts[1]) ?: [];
        $block = null;
        foreach ($lines as $line) {
            if ($block === null) {
                if (preg_match('~^  ' . preg_quote($job, '~') . ':\s*$~', $line) === 1) {
                    $block = [];
                }
                continue;
            }
            if (preg_match('~^ {0,2}\S~', $line) === 1) {
                break;
            }
            $block[] = $line;
        }

        return $block === null ? null : implode("\n", $block);
    }

    /** Valor escalar da primeira linha `chave: valor`, sem aspas. */
    public static function scalar(string $block, string $key): ?string
    {
        if (preg_match('~^\s*(?:-\s+)?' . preg_quote($key, '~') . ':[ \t]*(.*?)[ \t]*$~m', $block, $m) !== 1) {
            return null;
        }

        return self::unquote($m[1]);
    }

    /** Lista `chave: [a, b]` ou em linhas `- a`. @return list<string>|null */
    public static function listValue(string $block, string $key): ?array
    {
        $lines = preg_split('~\R~', $block) ?: [];
        foreach ($lines as $i => $line) {
            if (preg_match('~^(\s*)(?:-\s+)?' . preg_quote($key, '~') . ':[ \t]*(.*?)[ \t]*$~', $line, $m) !== 1) {
                continue;
            }
            $value = $m[2];
            if ($value !== '') {
                if (!str_starts_with($value, '[') || !str_ends_with($value, ']')) {
                    return [self::unquote($value)];
                }
                $inner = trim(substr($value, 1, -1));

                return $inner === '' ? [] : array_map(
                    static fn (string $item): string => self::unquote(trim($item)),
                    explode(',', $inner)
                );
            }
            $items = [];
            for ($j = $i + 1; $j < count($lines); $j++) {
                if (preg_match('~^\s*-\s+(.+?)\s*$~', $lines[$j], $item) !== 1) {
                    break;
                }
                $items[] = self::unquote($item[1]);
            }

            return $items;
        }

        return null;
    }

    public static function unquote(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    // ---------------------------------------------------------------- arquivos

    /**
     * Arquivos de texto da entrega (o que vai para o projeto criado), relativos à raiz.
     *
     * @return list<string>
     */
    public static function deliveredTextFiles(): array
    {
        $root = self::root();
        $ignoredDirs = ['vendor', '.git', 'node_modules', 'coverage', '.phpunit.cache', '.phpstan.cache', '.elavora'];
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $first = explode('/', $relative)[0];
            if (in_array($first, $ignoredDirs, true) || $relative === 'composer.lock' || $relative === '.env') {
                continue;
            }
            if (self::isSkeletonOnly($relative)) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            if (str_contains($content, "\0")) {
                continue;
            }
            $files[] = $relative;
        }
        sort($files);

        return $files;
    }

    public static function isSkeletonOnly(string $relative): bool
    {
        foreach (self::SKELETON_ONLY as $entry) {
            if ($relative === $entry || str_starts_with($relative, $entry . '/')) {
                return true;
            }
        }

        return false;
    }

    /** Remove o trecho entre as linhas de marcador, inclusive, preservando o resto byte a byte. */
    public static function withoutMarkedBlock(string $content, string $start, string $end): string
    {
        $lines = preg_split('~(?<=\n)~', $content) ?: [];
        $out = '';
        $inside = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!$inside && $trimmed === $start) {
                $inside = true;
                continue;
            }
            if ($inside) {
                if ($trimmed === $end) {
                    $inside = false;
                }
                continue;
            }
            $out .= $line;
        }

        return $out;
    }

    public static function tempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar {$dir}");
        }

        return $dir;
    }

    public static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }

    /** Copia a raiz do repositório para $target, sem vendor/ e .git/. */
    public static function copyRepository(string $target): void
    {
        $root = self::root();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $first = explode('/', $relative)[0];
            if (in_array($first, ['vendor', '.git', 'node_modules', 'coverage', '.phpunit.cache', '.phpstan.cache'], true)) {
                continue;
            }
            $dest = $target . '/' . $relative;
            if ($file->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0777, true);
                }
                continue;
            }
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0777, true);
            }
            copy($file->getPathname(), $dest);
        }
    }

    /**
     * Hash de cada arquivo da árvore, por caminho relativo.
     *
     * @return array<string, string>
     */
    public static function snapshot(string $dir): array
    {
        $result = [];
        if (!is_dir($dir)) {
            return $result;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $result[$relative] = (string) hash_file('sha256', $file->getPathname());
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * Executa um comando e devolve código, stdout e stderr.
     *
     * @param list<string> $command
     * @return array{code: int, stdout: string, stderr: string}
     */
    public static function run(array $command, ?string $cwd = null): array
    {
        // Saídas em arquivo: evita travar quando um dos pipes enche (ex.: docker build).
        $out = (string) tempnam(sys_get_temp_dir(), 'skout');
        $err = (string) tempnam(sys_get_temp_dir(), 'skerr');
        $process = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']],
            $pipes,
            $cwd
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Falha ao executar ' . implode(' ', $command));
        }
        $code = proc_close($process);
        $stdout = (string) file_get_contents($out);
        $stderr = (string) file_get_contents($err);
        @unlink($out);
        @unlink($err);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
