<?php

// The php.ini setting phar.readonly must be set to 0
$pharFile = 'aws-keys-rotate-into-github-secrets.phar';

// clean up
if (file_exists($pharFile)) {
    unlink($pharFile);
}
if (file_exists($pharFile.'.gz')) {
    unlink($pharFile.'.gz');
}

$phar = new Phar($pharFile);
$phar->startBuffering();
$defaultStub = $phar->createDefaultStub('rotate.php');
$phar->buildFromDirectory(__DIR__, '/(vendor|lib|rotate.php)/');
$stub = "#!/usr/bin/env php \n".$defaultStub;
$phar->setStub($stub);
$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);
chmod($pharFile, 0755);
rename($pharFile, str_replace('.phar', '', $pharFile));
