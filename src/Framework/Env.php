<?php

declare(strict_types=1);

namespace Slendie\Framework;

/**
 * Classe utilitária para gerenciar variáveis de ambiente.
 * 
 * Esta classe fornece métodos estáticos para carregar, obter e definir
 * variáveis de ambiente a partir de arquivos .env ou do sistema.
 * 
 * @packages Slendie\Framework
 */
final class Env
{
    /**
     * Array estático que armazena as variáveis de ambiente carregadas.
     * 
     * @var array<string, string>
     */
    private static array $vars = [];

    /**
     * Carrega variáveis de ambiente de um arquivo.
     * 
     * Lê um arquivo de configuração (geralmente .env) e extrai pares chave=valor,
     * ignorando linhas vazias e comentários (linhas que começam com #).
     * Os valores são automaticamente limpos de aspas simples, duplas e espaços.
     * 
     * As variáveis são armazenadas em três locais:
     * - Array interno da classe ($vars)
     * - Superglobal $_ENV
     * - Função putenv() do PHP
     * 
     * @param string $path Caminho para o arquivo de configuração (.env)
     * @return void
     */
    public static function load($path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (mb_strpos(mb_trim($line), '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = mb_trim($parts[0]);
                $value = mb_trim($parts[1]);
                $value = mb_trim($value, "\"' ");
                self::$vars[$key] = $value;
                $_ENV[$key] = $value;
                putenv($key.'='.$value);
            }
        }
    }

    /**
     * Obtém o valor de uma variável de ambiente.
     * 
     * Primeiro verifica se a variável existe no array interno ($vars),
     * caso contrário, tenta obter do ambiente do sistema através de getenv().
     * Se a variável não for encontrada, retorna o valor padrão fornecido.
     * 
     * @param string $key Nome da variável de ambiente
     * @param mixed $default Valor padrão a ser retornado se a variável não existir
     * @return mixed Valor da variável de ambiente ou o valor padrão
     */
    public static function get(string $key, mixed $default = null)
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $v = getenv($key);
        return $v !== false ? $v : $default;
    }

    /**
     * Define uma variável de ambiente.
     * 
     * Define o valor de uma variável de ambiente em três locais:
     * - Array interno da classe ($vars)
     * - Superglobal $_ENV
     * - Função putenv() do PHP
     * 
     * @param string $key Nome da variável de ambiente
     * @param string $value Valor a ser atribuído à variável
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::$vars[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key.'='.$value);
    }
}

if (!function_exists('env')) {
    /**
     * Função helper global para obter variáveis de ambiente.
     * 
     * Wrapper conveniente para Env::get() que permite acessar
     * variáveis de ambiente de forma mais simples e direta.
     * 
     * @param string $key Nome da variável de ambiente
     * @param mixed $default Valor padrão a ser retornado se a variável não existir
     * @return mixed Valor da variável de ambiente ou o valor padrão
     */
    function env($key, $default = null)
    {
        return Env::get($key, $default);
    }
}
