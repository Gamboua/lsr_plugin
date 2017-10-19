# LSR Sync Agent Plugin

- Plugin do Moodle que roda por cron de um e um minuto.

- Ele checa uma ou mais urls, procura o resultado json cria usuário, cria curso e faz a matricula.

- Necessário o arquivo admin/cron.php estar adicionado na cron para funcionar.

- Exite também um arquivo de log que será criado em /tmp/logs

- O curso é criado a partir de um backup do tipo .mbz que é baixado de uma URL.

- Para realizar a criação do curso, está sendo utilizado o moosh (https://moosh-online.com/)

- Plugin ainda estava em desenvolvimento
