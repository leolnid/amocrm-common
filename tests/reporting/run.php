<?php
declare(strict_types=1);
$php=PHP_BINARY;
$tests=[__DIR__.'/semantic.php',__DIR__.'/reporter-pipeline.php',__DIR__.'/nullable-project-link.php',__DIR__.'/http-failure-classifier.php',__DIR__.'/partial-coverage.php',__DIR__.'/outbox-delivery-diagnostics.php',__DIR__.'/telegram-proxy.php',__DIR__.'/root-cause.php'];
foreach($tests as $test){
 $cmd=[ $php, $test ];
 $proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($proc)){fwrite(STDERR,"Unable to start fixture\n");exit(2);}
 fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($proc);
 echo basename($test).': '.$out; if($err!=='')fwrite(STDERR,$err); if($code!==0)exit($code);
}
