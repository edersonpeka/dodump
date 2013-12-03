<?php

if ( !function_exists( '__' ) ) :
    function __( $t ) {
        return $t;
    }
endif;
if ( !function_exists( '_e' ) ) :
    function _e( $t ) {
        echo( __( $t ) );
        return __( $t );
    }
endif;
function posted( $field, $default = '' ) {
    return ( isset( $_POST[$field] ) && trim( $_POST[$field] ) ) ? $_POST[$field] : $default;
}
function matchConst( $const, $text ) {
    preg_match('/^\s*define\s*\(\s*[\'"]' . $const . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)\s*;/m', $text, $matches);
    if ( count( $matches ) > 1 ) return $matches[1];
    return false;
}

$ddstr = array(
	'test' => 'Test',
	'dump' => 'Dump!',
	'load' => 'Load...',
);

$msgret = '';

$WP_CONFIG = posted( 'ddConfigPath', 'wp-config.php' );
$DUMPFILE = posted( 'ddDumpFile', 'dumpfile.sql' );
$DOMAIN = posted( 'ddDomain', '' );
$PACK = posted( 'ddPack', 0 );
$SITEURL = '';
    
$acao = posted( 'acao' );
if ( $acao ) {

    $DB_NAME = '';
    $DB_USER = '';
    $DB_PASSWORD = '';
    $DB_HOST = '';
    if ( 'wp-config.php' == $WP_CONFIG && !file_exists( $WP_CONFIG ) ) {
        $WP_CONFIG = 'wordpress/wp-config.php';
        if ( !file_exists( $WP_CONFIG ) ) $WP_CONFIG = 'www/wp-config.php';
        if ( !file_exists( $WP_CONFIG ) ) $WP_CONFIG = 'public_html/wp-config.php';
        if ( !file_exists( $WP_CONFIG ) ) $WP_CONFIG = '../wordpress/wp-config.php';
        if ( !file_exists( $WP_CONFIG ) ) $WP_CONFIG = '../www/wp-config.php';
        if ( !file_exists( $WP_CONFIG ) ) $WP_CONFIG = '../public_html/wp-config.php';
    }

    $arrwpcontent = explode( '/', $WP_CONFIG );
    $arrwpcontent[ count( $arrwpcontent ) - 1 ] = 'wp-content';
    $WP_CONTENT = implode( '/', $arrwpcontent );
    
    if ( !trim( $WP_CONFIG ) ) {

        $msgret = 'Config file not specified.|error';
    
    } elseif( !file_exists( $WP_CONFIG ) ) {

        $msgret = 'Config file <strong>' . $WP_CONFIG . '</strong> not found.|error';

    } else {
        $confdata = file_get_contents( $WP_CONFIG );
        $dbName = matchConst( 'DB_NAME', $confdata );
        $dbUser = matchConst( 'DB_USER', $confdata );
        $dbPassword = matchConst( 'DB_PASSWORD', $confdata );
        $dbHost = matchConst( 'DB_HOST', $confdata );

        if ( $cn = php_conecta( $dbHost, $dbUser, $dbPassword, $dbName ) ) {
        
            if ( !$DOMAIN ) {
                $rs = php_retorna_um( "SELECT option_value FROM wp_options WHERE option_name='siteurl'" );
                $SITEURL = $rs['option_value'];
                $DOMAIN = preg_replace( '/https?\:\/\//im', '', $SITEURL );
            } else {
                $SITEURL = 'http://' . $DOMAIN;
            }
            
            if ( !trim( $SITEURL ) ) {
            
                $msgret = 'Could not retrieve your site URL from database, and [domain] was not specified.|error';

            } else {

                if ( __( $ddstr['dump'] ) == $acao ) {

                    if ( !file_exists( $DUMPFILE ) && !@touch( $DUMPFILE ) ) {
                        $msgret = 'Could not create dump file <strong>(' . $DUMPFILE . ').</strong>|error';
                    } elseif ( is_writable( $DUMPFILE ) ) {
            			$theDump = _mysqldump( $dbName );
            			$theDump = str_replace( array( $SITEURL, $DOMAIN ), array( '[[INSERT-SITEURL-HERE]]', '[[INSERT-DOMAIN-HERE]]' ), $theDump );
            			if ( file_put_contents( $DUMPFILE, $theDump ) ) {
                            $msgret = 'Dump written to <strong>' . $DUMPFILE . '</strong> file.|success';
                            if ( $PACK ) {
                                $arrlogfile = explode( '/', $DUMPFILE );
                                $arrlogfile[ count( $arrlogfile ) - 1 ] = 'dumpfile.log';
                                $DUMPFILELOG = implode( '/', $arrlogfile );
                                if ( !file_exists( $DUMPFILELOG ) && !@touch( $DUMPFILELOG ) ) $DUMPFILELOG = '/dev/null';
                                $PACK_CMD = 'tar -cvzf dumpfile.tgz ' . $DUMPFILE . ' ';
                                if ( $PACK > 1 ) {
                                    if ( !file_exists( $WP_CONTENT ) ) {
                                        $WP_CONTENT = 'wordpress/wp-content';
                                        if ( !file_exists( $WP_CONTENT ) ) $WP_CONTENT = 'www/wp-content';
                                        if ( !file_exists( $WP_CONTENT ) ) $WP_CONTENT = 'public_html/wp-content';
                                        if ( !file_exists( $WP_CONTENT ) ) $WP_CONTENT = '../wordpress/wp-content';
                                        if ( !file_exists( $WP_CONTENT ) ) $WP_CONTENT = '../www/wp-content';
                                        if ( !file_exists( $WP_CONTENT ) ) $WP_CONTENT = '../public_html/wp-content';
                                    }
                                    $PACK_CMD .= $WP_CONTENT . '/uploads ';
                                    if ( $PACK > 2 ) $PACK_CMD .= $WP_CONTENT . '/themes ' . $WP_CONTENT . '/plugins ';
                                }
                                shell_exec( $PACK_CMD . ' > ' . $DUMPFILELOG . ' 2>/dev/null &' );
                                $msgret = 'Dump written to <strong>' . $DUMPFILE . '</strong> file. Also, a background process must be creating a <strong>dumpfile.tgz</strong> file right now!|success';
                            }
            			} else {
                            $msgret = 'Error writing dump file <strong>(' . $DUMPFILE . ').</strong>|error';
            			}
            		} else {
                        $msgret = 'Could not write dump file <strong>(' . $DUMPFILE . ').</strong>|error';
                    }

                } elseif ( __( $ddstr['load'] ) == $acao ) {

                    if ( !file_exists( $DUMPFILE ) ) {
                        $msgret = 'Dump file <strong>(' . $DUMPFILE . ')</strong> does not exist.|error';
                    } elseif( !is_readable( $DUMPFILE ) ) {
                        $msgret = 'Could not read dump file <strong>(' . $DUMPFILE . ').</strong>|error';
                    } else {
                        $theDump = file_get_contents( $DUMPFILE );
            			$theDump = str_replace( array( '[[INSERT-SITEURL-HERE]]', '[[INSERT-DOMAIN-HERE]]' ), array( $SITEURL, $DOMAIN ), $theDump );
                        $count = _mysql_load( $theDump );
                        $msgret = 'Dump loaded from file <strong>' . $DUMPFILE . '.</strong> ' . $count . ' queries executed.|success';
                    }

                } else {
                    $msgret = 'Connection successful!|success';
                }
            
            }

            php_desconecta( $cn );

        } else {

            $msgret = 'Could not connect.|error';

        }

    }

}

header('Content-Type: text/html; charset=utf-8');
?>
<html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<title>DO-DUMP!</title>
<link rel="stylesheet" href="http://ederson.peka.nom.br/stuff/bootstrap.1.3.0.min.css" />
</head>
<body>

<div class="container">

    <?php if ( $msgret ) : ?>
        <?php $amsgret = explode( '|', $msgret ); $msgret = array_shift( $amsgret ); ?>
        <div class="alert-message <?php _e( implode( ' ', $amsgret ) ); ?>">
            <p><?php _e( str_replace( "\n", "<br />\n", $msgret ) ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="form-stacked">
        <fieldset>
            <legend><?php _e( 'DO-DUMP!' ); ?></legend>

            <div class="clearfix">
                <label for="ddConfigPath"><?php _e( 'Path to wp-config.php:' ); ?></label>
                <div class="input">
                  <input class="xlarge" id="ddConfigPath" name="ddConfigPath" size="30" type="text" placeholder="<?php _e( 'Default: autodetect' ); ?>" />
                </div>
            </div>

            <div class="clearfix">
                <label for="ddDumpFile"><?php _e( 'Dump file:' ); ?></label>
                <div class="input">
                  <input class="xlarge" id="ddDumpFile" name="ddDumpFile" size="30" type="text" placeholder="<?php _e( 'Default: dumpfile.sql' ); ?>" />
                </div>
            </div>

            <div class="clearfix">
                <label for="ddDomain"><?php _e( 'Domain:' ); ?></label>
                <div class="input">
                  <input class="xlarge" id="ddDomain" name="ddDomain" size="30" type="text" placeholder="<?php _e( 'Default: autodetect' ); ?>" />
                </div>
            </div>

            <?php if ( '/' == DIRECTORY_SEPARATOR ) : // Windows não, mamãe! ?>

                <div class="clearfix">
                    <label for="ddPack"><?php _e( 'Compress:' ); ?></label>
                    <div class="input">
                      <select id="ddPack" name="ddPack">
                        <option value="0"><?php _e( 'Do nothing' ); ?></option>
                        <option value="1"><?php _e( 'Compress .sql file to "dumpfile.tgz" file' ); ?></option>
                        <option value="2"><?php _e( 'Pack .sql file and "wp-content/uploads" folder in "dumpfile.tgz" file' ); ?></option>
                        <option value="3"><?php _e( 'Pack .sql file and "wp-content/uploads", "wp-content/plugins" and "wp-content/themes" folders in "dumpfile.tgz" file' ); ?></option>
                      </select>
                    </div>
                </div>

            <?php endif; ?>
          
            <input type="submit" name="acao" value="<?php _e( $ddstr['test'] ); ?>" class="btn primary" />
            <input type="submit" name="acao" value="<?php _e( $ddstr['dump'] ); ?>" class="btn primary" />
            <input type="submit" name="acao" value="<?php _e( $ddstr['load'] ); ?>" class="btn danger" onclick="return confirm('<?php _e( 'This is gonna PERMANENTLY overwrite your database information. Are you sure?'); ?>');" />
        </fieldset>
    </form>

</div>

</body>
</html>

<?php


//Retorna um objeto de conexão, usando os parâmetros de "parametros.php"
function php_conecta( $db_server, $db_user, $db_password, $db_database ) {
	if ( ! $conexao = mysql_connect( $db_server, $db_user, $db_password ) ) return false;
	if ( ! mysql_select_db( $db_database, $conexao ) ) return false;
	php_executa( "SET NAMES utf8" );
	/*
	php_executa( "SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED" );
	*/
	return $conexao;
}

//Fecha o objeto de conexão "$conexao"
function php_desconecta( $conexao ){
	mysql_close( $conexao );
}

//Retorna o resultado da consulta SQL "$sqlq"
function php_executa( $sqlq ){
	return mysql_query( $sqlq );
}

//Retorna o resultado da consulta SQL "$sqlq"
function php_retorna($sqlq){
        $arr = array();
        //$sqlq = str_replace( "SELECT ", "SELECT SQL_CACHE ", $sqlq );
        $rs = php_executa( $sqlq );
        while ( $row = mysql_fetch_array( $rs ) ) $arr[] = $row;
        mysql_free_result( $rs );
        return $arr;
}

//Retorna o resultado da consulta SQL "$sqlq"
function php_retorna_um($sqlq){
    if ( $rs = php_retorna( $sqlq ) ) return $rs[0];
    return false;
}



////////////////////////////////////////////////////////////////////////////////////////////////////

/*---------------------------------------------------+
| mysqldump.php
+----------------------------------------------------+
| Copyright 2006 Huang Kai
| hkai@atutility.com
| http://atutility.com/
+----------------------------------------------------+
| Released under the terms & conditions of v2 of the
| GNU General Public License. For details refer to
| the included gpl.txt file or visit http://gnu.org
+----------------------------------------------------*/
/*
change log:
2006-10-16 Huang Kai
---------------------------------
initial release

2006-10-18 Huang Kai
---------------------------------
fixed bugs with delimiter
add paramter header to add field name as CSV file header.

2006-11-11 Huang Kia
---------------------------------
Tested with IE and fixed the <button> to <input>

2011-09-27 Ederson Peka
---------------------------------
Functions return values (don't print/echo data).
*/

function _mysqldump_csv($table)
{
    $ret = '';
	$delimiter= ",";
	if( isset($_REQUEST['csv_delimiter']))
		$delimiter= $_REQUEST['csv_delimiter'];
	
	if( 'Tab' == $delimiter)
		$delimiter="\t";
	
	
	$sql="select * from `$table`;";
	$result=mysql_query($sql);
	if( $result)
	{
		$num_rows= mysql_num_rows($result);
		$num_fields= mysql_num_fields($result);
		
		$i=0;
		while( $i < $num_fields)
		{
			$meta= mysql_fetch_field($result, $i);
			$ret .= ($meta->name);
			if( $i < $num_fields-1)
				$ret .=  "$delimiter";
			$i++;
		}
		$ret .=  "\n";
		
		if( $num_rows > 0)
		{
			while( $row= mysql_fetch_row($result))
			{
				for( $i=0; $i < $num_fields; $i++)
				{
					$ret .=  mysql_real_escape_string($row[$i]);
					if( $i < $num_fields-1)
							$ret .=  "$delimiter";
				}
				$ret .=  "\n";
			}
			
		}
	}
	mysql_free_result($result);
	return $ret;
}	


function _mysqldump( $mysql_database, $tabledata = true )
{
    $mysqldump_version = "1.02";
    $ret = "/* mysqldump.php version $mysqldump_version */\n";
	$sql = "SHOW TABLES;";
	$result = mysql_query( $sql );
	if ( $result )
	{
		while ( $row = mysql_fetch_row( $result ) )
		{
			$ret .= _mysqldump_table_structure( $row[0] );
			
			if ( $tabledata )
			{
				$ret .= _mysqldump_table_data( $row[0] );
			}
		}
	}
	else
	{
		$ret .= "/* no tables in $mysql_database */\n";
	}
	mysql_free_result( $result );
	return $ret;
}

function _mysqldump_table_structure( $table, $droptable = true, $createtable = true )
{
    $ret = '';
	$ret .= "/* Table structure for table `$table` */\n";
	if ( $droptable )
	{
		$ret .= "DROP TABLE IF EXISTS `$table`;\n\n";
	}	
	if ( $createtable )
	{
	
		$sql = "SHOW CREATE TABLE `$table`; ";
		$result = mysql_query( $sql );
		if ( $result )
		{
			if ( $row = mysql_fetch_assoc($result) )
			{
				$ret .= $row['Create Table'].";\n\n";
			}
		}
		mysql_free_result( $result );
	}
	return $ret;
}

function _mysqldump_table_data($table)
{
	$ret = '';
	$sql = "SELECT * FROM `$table`;";
	$result = mysql_query( $sql );
	if ( $result )
	{
		$num_rows = mysql_num_rows( $result );
		$num_fields = mysql_num_fields( $result );
		
		if ( $num_rows > 0)
		{
			$ret .=  "/* dumping data for table `$table` */\n";
			
			$field_type = array();
			$i = 0;
			while ( $i < $num_fields )
			{
				$meta = mysql_fetch_field( $result, $i );
				array_push( $field_type, $meta->type );
				$i++;
			}
			
			//print_r( $field_type);
			$ret .=  "INSERT INTO `$table` VALUES\n";
			$index = 0;
			while ( $row = mysql_fetch_row( $result ) )
			{
				$ret .= "(";
				for ( $i=0; $i < $num_fields; $i++ )
				{
					if ( is_null( $row[$i] ) )
						$ret .= "null";
					else
					{
						switch ( $field_type[$i] )
						{
							case 'int':
								$ret .= $row[$i];
								break;
							case 'string':
							case 'blob' :
							default:
								$ret .= "'" . mysql_real_escape_string( $row[$i] ) . "'";
								
						}
					}
					if ( $i < $num_fields-1 )
						$ret .= ",";
				}
				$ret .= ")";
				
				if ( $index < $num_rows-1 )
					$ret .= ",";
				else
					$ret .= ";";
				$ret .= "\n";

				$index++;
			}
		}
	}
	mysql_free_result( $result );
	$ret .= "\n";
	return $ret;
}

function _mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password)
{
	$output_messages = array();
	$link = mysql_connect($mysql_host, $mysql_username, $mysql_password);
	if (!$link) 
	{
	   array_push($output_messages, 'Could not connect: ' . mysql_error());
	}
	else
	{
		array_push ($output_messages,"Connected with MySQL server:$mysql_username@$mysql_host successfully");
	
		$db_selected = mysql_select_db($mysql_database, $link);
		if (!$db_selected) 
		{
			array_push ($output_messages,'Can\'t use $mysql_database : ' . mysql_error());
		}
		else
			array_push ($output_messages,"Connected with MySQL database:$mysql_database successfully");
	}
	return $output_messages;
	
}

function _mysql_load( $text ) {
    $READ = explode ( ";\n", $text ); 
    $c = 0;
    foreach ( $READ as $RED ) { 
        $RED = trim( preg_replace( '/^--[^\n]*\n/ims', '', $RED ) );
        if ( $RED ) {
            //echo '<!-- ' . $RED . ' -->' . "\n";
            mysql_query ( $RED );
            $c++;
        }
    }
    return $c;
}

?>
