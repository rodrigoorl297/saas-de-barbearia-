<?php
/**
 * PSR-4 Autoloader Nativo
 */
spl_autoload_register(function ($class) {
    // prefixo do projeto (namespace base)
    $prefix = 'App\\';

    // diretório base correspondente ao prefixo
    $base_dir = __DIR__ . '/src/';

    // a classe usa o prefixo?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // não, muda para o próximo autoloader
        return;
    }

    // pega o nome relativo da classe
    $relative_class = substr($class, $len);

    // substitui os separadores de namespace pelos de diretório,
    // e adiciona a extensão .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // se o arquivo existir, requer-o
    if (file_exists($file)) {
        require $file;
    }
});
