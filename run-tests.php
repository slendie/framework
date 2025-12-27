<?php

/**
 * Wrapper script para executar Pest e garantir que as estatísticas sejam exibidas
 * 
 * Este script resolve o problema onde o Pest não exibe estatísticas finais
 * no Windows/PowerShell devido a problemas com output buffering ou formatters.
 */

// Desativa output buffering completamente
if (ob_get_level() > 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

// Executa o Pest
$command = 'vendor\bin\pest';
$args = array_slice($argv, 1);
if (!empty($args)) {
    $command .= ' ' . implode(' ', array_map('escapeshellarg', $args));
}

// Verifica se tem algum formato de output especificado
$hasOutputFormat = false;
$outputFormats = ['--compact', '--testdox', '--teamcity', '--debug', '--no-progress'];
foreach ($outputFormats as $format) {
    if (in_array($format, $args)) {
        $hasOutputFormat = true;
        break;
    }
}

// Para --profile, sempre adiciona --compact para garantir que as estatísticas sejam exibidas
if (in_array('--profile', $args)) {
    if (!in_array('--compact', $args)) {
        $command .= ' --compact';
    }
} elseif (in_array('--coverage', $args) || in_array('--coverage-text', $args)) {
    // Para --coverage, usa --coverage-text que é mais estável
    // O --coverage sozinho pode causar problemas com objetos Request sendo serializados
    if (!in_array('--coverage-text', $args) && in_array('--coverage', $args)) {
        // Remove --coverage e adiciona --coverage-text
        $command = str_replace(' --coverage', '', $command);
        $command .= ' --coverage-text';
    }
    if (!in_array('--compact', $args)) {
        $command .= ' --compact';
    }
} elseif (!$hasOutputFormat) {
    // Se não tiver nenhum formato, adiciona --compact
    $command .= ' --compact';
}

// Executa e captura output
$output = [];
$returnCode = 0;
exec($command . ' 2>&1', $output, $returnCode);

// Exibe output linha por linha para garantir que tudo seja exibido
foreach ($output as $line) {
    echo $line . PHP_EOL;
}

// Força flush final
flush();

exit($returnCode);

