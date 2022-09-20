<?php
ob_start();
header('Content-Type: text/html');

// Verifica requisiti
if( $_GET['op'] == 'check' ) {
    $all_ok = true;

    while (@ob_end_flush()){
        /**
         * 1) Verifica connessione MySQL
         */
        // TODO: verifica requisiti PHP

        // TODO: verifica se il file da scaricare è corretto

        // TODO: verifica versione MySQL (>= 5.6 e <= 8.0)

        // Verifica se i dati del database sono corretti
        echo '🔌 Verifica connessione SQL... ';
        flush();
        
        $mysqli = new mysqli($_POST['db_host'], $_POST['db_username'], $_POST['db_password'], $_POST['db_name']);

        // Check connection
        if ($mysqli -> connect_errno) {
            $all_ok = false;
            echo '❌<br>'.$mysqli->connect_error;
        } else {
            echo '✔️';
        }
        echo '<br>';
        flush();


        /**
         * 2) Download ZIP
         */
        echo '📥 Scaricamento file... ';
        flush();

        $fh = fopen("backup.zip", "w");
        if (false === $fh){
            $all_ok = false;
            echo '❌<br>Impossibile salvare il file!';
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_URL, str_replace(' ', '%20', $_POST['source']));
            curl_setopt($ch, CURLOPT_FILE, $fh);
            curl_exec($ch);
            if (curl_errno($ch)) {
                $all_ok = false;
                echo '❌<br>'.curl_error($ch);
            } else {
                echo '✔️';
            }

            curl_close($ch);
        }

        echo '<br>';
        flush();

        if ($all_ok) {
            echo '
            <div class="text-center">
                <button type="button" class="btn btn-success btn-lg" onclick="post(\'?op=restore\', $(\'form\').serialize(), true );">Avvia ripristino!</button>
            </div>';
        }
        flush();

        continue;
    }

    exit();
}


elseif( $_GET['op'] == 'restore'){
    $skip_permissions = true;

    while (@ob_end_flush()){
        /**
         * 3) Unzip
         */
        echo '🗜️ Decompressione file... ';
        flush();

        $zip = new ZipArchive;
        $res = $zip->open('backup.zip');

        if ($res === TRUE) {
            $zip->extractTo('.');
            $zip->close();
            echo '✔️';
        } else {
            echo '❌<br>'.$zip->getStatusString();
        }

        echo '<br>';
        flush();


        /**
         * 5) Creazione file config.in.php
         */
        echo '🧹 Creazione file config.inc.php... ';
        flush();

        copy('config.example.php', 'config.inc.php');
        $config = file_get_contents('config.inc.php');

        $config = str_replace('|host|', $_POST['db_host'], $config);
        $config = str_replace('|username|', $_POST['db_username'], $config);
        $config = str_replace('|password|', $_POST['db_password'], $config);
        $config = str_replace('|database|', $_POST['db_name'], $config);

        if ( file_put_contents('config.inc.php', $config) ) {
            echo '✔️';
        } else {
            echo '❌<br>error';
        }

        echo '<br>';
        flush();


        /**
         * 5) TODO: Ripristino database
         */
        echo "🛟 Ripristino database... <div id='progress-db' class='progress'><div class='progress-bar' style='width:0%;'></div></div>\n";
        flush();

        $queries = readSQLFile('database.sql', ';');
        $count = count($queries);
        
        for ($i = 0; $i < $count; ++$i) {
            $percent = 0;
            if ($i > 0) {
                $percent = $i/$count*100;
            }
            
            try {
                $database->query($queries[$i]);
                echo "<script>$('#progress-db > div').css('width', '".round($percent, 2)."%').text('".round($percent, 2)."%');</script>\n";
                flush();
            } catch (\Exception $e) {
                echo '❌<br>'.$queries[$i];
                flush();
            }
        }

        echo '✔️';
        echo '<br>';
        flush();

        /**
         * 6) Eliminazione file temporanei
         */
        echo '🧹 Pulizia file...<br>';
        flush();

        echo '- backup.zip ';
        if ( @unlink('backup.zip') ) {
            echo '✔️';
        } else {
            echo '❌<br>error';
        }
        echo '<br>';
        flush();

        echo '- restore.php ';
        if ( @unlink('restore.php') ) {
            echo '✔️';
        } else {
            echo '❌<br>error';
        }

        echo '<br>';
        flush();

        exit();
    }

    exit();
}

?>

<!doctype html>
<html class="no-js" lang="it">
    <head>
        <meta charset="utf-8">
        <title>OSM restore</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- CSS only -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor" crossorigin="anonymous">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    </head>

    <body>
        <div class="container">
            <h1>OpenSTAManager restore</h1>
            <p class="lead">Ripristina il tuo OpenSTAManager su questo server.</p>

            <form action="" method="post">
                <div class="row">
                    <div class="col">
                        <div class="h-100 p-5 bg-light border rounded-3">
                            <h3>Sorgente</h3>
                                <label class="form-label">Percorso backup:</label>
                                <input type="text" class="form-control" name="source" placeholder="https://ilmioosm.it/backup/OSM Backup 2022-01-01 01:00:00.zip">
                            </form>
                        </div>
                    </div>

                    <div class="col">
                        <div class="h-100 p-5 bg-light border rounded-3">
                            <h3>Destinazione</h3>
                            <div class="mb-3">
                                <label class="form-label">Cartella:</label>
                                <input type="text" class="form-control" name="destination" value="<?php echo dirname( $_SERVER['SCRIPT_FILENAME'] ); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">DATABASE HOST:</label>
                                <input type="text" class="form-control" name="db_host" placeholder="localhost">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">DATABASE USERNAME:</label>
                                <input type="text" class="form-control" name="db_username">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">DATABASE PASSWORD:</label>
                                <input type="text" class="form-control" name="db_password">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">DATABASE NAME:</label>
                                <input type="text" class="form-control" name="db_name">
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                
                <div class="text-center">
                    <button type="button" class="btn btn-primary" id="btn-check">Verifica requisiti</button>
                </div>

                <div id="results"></div>
            </form>
        </div>

        <script>
            $(document).ready( function(){
                $('#btn-check').click( function(){
                    post( '?op=check', $('form').serialize(), false );
                });
            });

                

            function post( url, data, append ){
                if (!append) {
                    $('#results').html('');
                }

                var lastResponseLength = false;
                var ajaxRequest = $.ajax({
                    type: 'post',
                    url: url,
                    data: data,
                    processData: false,
                    xhrFields: {
                        // Getting on progress streaming response
                        onprogress: function(e)
                        {
                            var progressResponse;
                            var response = e.currentTarget.response;
                            if(lastResponseLength === false)
                            {
                                progressResponse = response;
                                lastResponseLength = response.length;
                            }
                            else
                            {
                                progressResponse = response.substring(lastResponseLength);
                                lastResponseLength = response.length;
                            }
                            var parsedResponse = progressResponse;
                            $('#results').append(parsedResponse);
                        }
                    }
                });

                // On completed
                /*
                ajaxRequest.done(function(data)
                {
                    console.log('Complete response = ' + data);
                });
                */

                // On failed
                ajaxRequest.fail(function(error){
                    console.log('Error: ', error);
                });
            }
        </script>
    </body>
</html>

<?php

if (!function_exists('readSQLFile')) {
    /**
     * Restituisce l'insieme delle query presente nel file specificato.
     *
     * @param string $filename  Percorso per il file
     * @param string $delimiter Delimitatore delle query
     *
     * @since 2.3
     *
     * @return array
     */
    function readSQLFile($filename, $delimiter = ';')
    {
        $inString = false;
        $escChar = false;
        $query = '';
        $stringChar = '';
        $queryLine = [];
        $queryBlock = file_get_contents($filename);
        $sqlRows = explode("\n", $queryBlock);
        $delimiterLen = strlen($delimiter);
        do {
            $sqlRow = current($sqlRows)."\n";
            $sqlRowLen = strlen($sqlRow);
            for ($i = 0; $i < $sqlRowLen; ++$i) {
                if ((substr(ltrim($sqlRow), $i, 2) === '--') && !$inString) {
                    break;
                }
                $znak = substr($sqlRow, $i, 1);
                if ($znak === '\'' || $znak === '"') {
                    if ($inString) {
                        if (!$escChar && $znak === $stringChar) {
                            $inString = false;
                        }
                    } else {
                        $stringChar = $znak;
                        $inString = true;
                    }
                }
                if ($znak === '\\' && substr($sqlRow, $i - 1, 2) !== '\\\\') {
                    $escChar = !$escChar;
                } else {
                    $escChar = false;
                }
                if (substr($sqlRow, $i, $delimiterLen) === $delimiter) {
                    if (!$inString) {
                        $query = trim($query);
                        $delimiterMatch = [];
                        if (preg_match('/^DELIMITER[[:space:]]*([^[:space:]]+)$/i', $query, $delimiterMatch)) {
                            $delimiter = $delimiterMatch[1];
                            $delimiterLen = strlen($delimiter);
                        } else {
                            $queryLine[] = $query;
                        }
                        $query = '';
                        continue;
                    }
                }
                $query .= $znak;
            }
        } while (next($sqlRows) !== false);

        return $queryLine;
    }
}
