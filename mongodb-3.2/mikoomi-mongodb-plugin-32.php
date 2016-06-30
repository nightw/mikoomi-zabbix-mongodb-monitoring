<?php

/**********************************************************************
                       Mikoomi MIT License
**********************************************************************
Copyright (c) 2011 by Jayesh Thakrar

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

************************************************************************/

error_reporting(E_PARSE) ;
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

$options = getopt("Dh:p:z:u:x:H:P:", array("ssl")) ;
$command_name = basename($argv[0]) ;
$command_version = "0.8" ;

// Get data collection start time (we will use this to compute the total data collection time)
$start_time = time() ;

// At a minimum, we need to get the Zabbix hostname. If not, display usage message.
if (empty($options) or empty($options['z']) ) {
    echo "
$command_name Version $command_version
Usage : $command_name [-D] [-h <mongoDB Server Host>] [-p <mongoDB Port>] [--ssl] [-u <username>] [-x <password>] [-H <Zabbix Server ip/hostname>] [-P <Zabbix Server Port>] -z <Zabbix_Name>
where
   -D    = Run in detail/debug mode
   -h    = Hostname or IP address of server running MongoDB
   -p    = Port number on which to connect to the mongod or mongos process
   -z    = Name (hostname) of MongoDB instance or cluster in the Zabbix UI
   -u    = User name for database authentication
   -x    = Password for database authentication
   -H    = Zabbix server IP or hostname
   -P    = Zabbix server Port or hostname
   --ssl = Use SSL when connecting to MongoDB
"  ;

exit ;
}

//-------------------------------------------------------------------------//
// Setup log file, data file and zabbix hostname.
//-------------------------------------------------------------------------//
$zabbix_name = $options['z'] ;

// Remove spaces from zabbix name for file data and log file creation
$file_base_name = str_replace(' ', '_', $zabbix_name);

$zabbix_server = ($options['H'] ? $options['H'] : '127.0.0.1');
$zabbix_server_port = ($options['P'] ? $options['P'] : '10051');

$debug_mode = isset($options['D']) ;

$ssl = isset($options['ssl']) ;

if ($ssl && !MONGO_SUPPORTS_SSL) {
  echo "WARNING: --ssl option is specified, but we will not use it, because the PHP Mongo extension does not support SSL!\n" ;
  $ssl = false ;
}

$data_lines = array() ;

$md5_checksum_string = md5_file($argv[0]) ;

if ($debug_mode) {
    write_to_log("version $command_version") ;
}
//-------------------------------------------------------------------------//

//-------------------------------------------------------------------------//
function write_to_log($output_line)
//-------------------------------------------------------------------------//
{
    global $command_name ;
    fprintf(STDERR, "%s: %s\n", $command_name, $output_line) ;
}
//-------------------------------------------------------------------------//



//-------------------------------------------------------------------------//
function write_to_data_lines($zabbix_name, $key, $value)
//-------------------------------------------------------------------------//
{
    global $data_lines ;

    // Only if we have a value do we want to record this metric
    if(isset($value) && $value !== '')
    {
        $data_line = sprintf("\"%s\" \"mongodb.%s\" \"%s\"", $zabbix_name, $key, $value) ;
        $data_lines[] = $data_line ;
    }
}
//-------------------------------------------------------------------------//


//-------------------------------------------------------------------------//
// Now starts the heart of mongoDB monitoring !!
//-------------------------------------------------------------------------//

//-----------------------------
// Setup connection to mongoDB
//-----------------------------
//print ("Here in mikoomi mongo plugin ... before connecting mongo");

$mongodb_host = empty($options['h']) ? Mongo::DEFAULT_HOST : $options['h'] ;
$mongodb_port = empty($options['p']) ? Mongo::DEFAULT_PORT : $options['p']  ;

if ((!empty($options['u'])) && (!empty($options['x']))) {
    $connect_string = $options['u'] . ':' . $options['x'] . '@' . $mongodb_host . ':' . $mongodb_port  ;
}
else {
    $connect_string = $mongodb_host . ':' . $mongodb_port ;
}

if ($ssl) {
  $connect_string .= "/?ssl=true" ;
}
//print ("Mongo connect string - " . $connect_string);
#$mongo_connection = new Mongo("mongodb://$connect_string") ;
$mongo_connection = new MongoClient("mongodb://$connect_string") ;

if (is_null($mongo_connection)) {
    write_to_log("Error in connection to mongoDB using connect string $connect_string") ;
    exit ;
}
else {
    write_to_log("Successfully connected to mongoDB using connect string $connect_string") ;
}


//-----------------------------
// Get server statistics
//-----------------------------
$mongo_db_handle = $mongo_connection->selectDB("admin") ;

$server_status = $mongo_db_handle->command(array('serverStatus'=>1)) ;

if (!isset($server_status['ok'])) {
    write_to_log("Error in executing $command.") ;
    exit ;
}

//print ("************ ");
//print_r( $server_status ) ;

$mongo_version = $server_status['version'] ;
write_to_data_lines($zabbix_name, "version", $mongo_version) ;

$uptime = $server_status['uptime'] ;
write_to_data_lines($zabbix_name, "uptime", $uptime) ;

/*
if ($server_status['globalLock']['totalTime'] != null) {
  $globalLock_lockTime = $server_status['globalLock']['totalTime'] ;
  write_to_data_lines($zabbix_name, "globalLock.totalTime", $globalLock_totalTime) ;
}
*/

if ($server_status['globalLock']['totalTime'] != null) {
  $globalLock_totalTime = $server_status['globalLock']['totalTime'] ;
  write_to_data_lines($zabbix_name, "globalLock.totalTime", $globalLock_totalTime) ;
}

$globalLock_currentQueue_total = $server_status['globalLock']['currentQueue']['total'] ;
write_to_data_lines($zabbix_name, "globalLock.currentQueue.total", $globalLock_currentQueue_total) ;

$globalLock_currentQueue_readers = $server_status['globalLock']['currentQueue']['readers'] ;
write_to_data_lines($zabbix_name, "globalLock.currentQueue.readers", $globalLock_currentQueue_readers) ;

$globalLock_currentQueue_writers = $server_status['globalLock']['currentQueue']['writers'] ;
write_to_data_lines($zabbix_name, "globalLock.currentQueue.writers", $globalLock_currentQueue_writers) ;

$mem_bits = $server_status['mem']['bits'] ;
write_to_data_lines($zabbix_name, "mem.bits", $mem_bits) ;

$mem_resident = $server_status['mem']['resident'] ;
write_to_data_lines($zabbix_name, "mem.resident", $mem_resident) ;

$mem_virtual = $server_status['mem']['virtual'] ;
write_to_data_lines($zabbix_name, "mem.virtual", $mem_virtual) ;

$connections_current = $server_status['connections']['current'] ;
write_to_data_lines($zabbix_name, "connections.current", $connections_current) ;

$connections_available = $server_status['connections']['available'] ;
write_to_data_lines($zabbix_name, "connections.available", $connections_available) ;

$extra_info_heap_usage = round(($server_status['extra_info']['heap_usage_bytes'])/(1024*124), 2) ;
write_to_data_lines($zabbix_name, "extra_info.heap_usage", $extra_info_heap_usage) ;

$extra_info_page_faults = $server_status['extra_info']['page_faults'];
write_to_data_lines($zabbix_name, "extra_info.page_faults", $extra_info_page_faults) ;

/*if ($server_status['indexCounters']['btree']['accesses'] != null) {
  $indexCounters_btree_accesses = $server_status['indexCounters']['btree']['accesses'] ;
} else {
  $indexCounters_btree_accesses = $server_status['indexCounters']['accesses'] ;
}
write_to_data_lines($zabbix_name, "indexCounters.btree.accesses", $indexCounters_btree_accesses) ;

if ($server_status['indexCounters']['btree']['hits'] != null) {
  $indexCounters_btree_hits = $server_status['indexCounters']['btree']['hits'] ;
} else {
  $indexCounters_btree_hits = $server_status['indexCounters']['hits'] ;
}
write_to_data_lines($zabbix_name, "indexCounters.btree.hits", $indexCounters_btree_hits) ;

if ($server_status['indexCounters']['btree']['misses'] != null) {
  $indexCounters_btree_misses = $server_status['indexCounters']['btree']['misses'] ;
} else {
  $indexCounters_btree_misses = $server_status['indexCounters']['misses'] ;
}
write_to_data_lines($zabbix_name, "indexCounters.btree.misses", $indexCounters_btree_misses) ;

if ($server_status['indexCounters']['btree']['resets'] != null) {
  $indexCounters_btree_resets = $server_status['indexCounters']['btree']['resets'] ;
} else {
  $indexCounters_btree_resets = $server_status['indexCounters']['resets'] ;
}
write_to_data_lines($zabbix_name, "indexCounters.btree.resets", $indexCounters_btree_resets) ;

if ($server_status['indexCounters']['btree']['missRatio'] != null) {
  $indexCounters_btree_missRatio = $server_status['indexCounters']['btree']['missRatio'] ;
} else {
  $indexCounters_btree_missRatio = $server_status['indexCounters']['missRatio'] ;
}
write_to_data_lines($zabbix_name, "indexCounters.btree.missRatio", $indexCounters_btree_missRatio) ;
*/

/*$backgroundFlushing_flushes = $server_status['backgroundFlushing']['flushes'] ;
write_to_data_lines($zabbix_name, "backgroundFlushing.flushes", $backgroundFlushing_flushes) ;

$backgroundFlushing_total_ms = $server_status['backgroundFlushing']['total_ms'] ;
write_to_data_lines($zabbix_name, "backgroundFlushing.total_ms", $backgroundFlushing_total_ms) ;

$backgroundFlushing_average_ms = $server_status['backgroundFlushing']['average_ms'] ;
write_to_data_lines($zabbix_name, "backgroundFlushing.average_ms", $backgroundFlushing_average_ms) ;

$backgroundFlushing_last_ms = $server_status['backgroundFlushing']['last_ms'] ;
write_to_data_lines($zabbix_name, "backgroundFlushing.last_ms", $backgroundFlushing_last_ms) ;
*/
/*
$cursors_totalOpen = $server_status['cursors']['totalOpen'] ;
write_to_data_lines($zabbix_name, "cursors.totalOpen", $cursors_totalOpen) ;

$cursors_clientCursors_size = $server_status['cursors']['clientCursors_size'] ;
write_to_data_lines($zabbix_name, "cursors.clientCursors_size", $cursors_clientCursors_size) ;

$cursors_timedOut = $server_status['cursors']['timedOut'] ;
write_to_data_lines($zabbix_name, "cursors.timedOut", $cursors_timedOut) ;
*/
$opcounters_insert = $server_status['opcounters']['insert'] ;
write_to_data_lines($zabbix_name, "opcounters.insert", $opcounters_insert) ;

$opcounters_query = $server_status['opcounters']['query'] ;
write_to_data_lines($zabbix_name, "opcounters.query", $opcounters_query) ;

$opcounters_update = $server_status['opcounters']['update'] ;
write_to_data_lines($zabbix_name, "opcounters.update", $opcounters_update) ;

$opcounters_delete = $server_status['opcounters']['delete'] ;
write_to_data_lines($zabbix_name, "opcounters.delete", $opcounters_delete) ;

$opcounters_getmore = $server_status['opcounters']['getmore'] ;
write_to_data_lines($zabbix_name, "opcounters.getmore", $opcounters_getmore) ;

$opcounters_command = $server_status['opcounters']['command'] ;
write_to_data_lines($zabbix_name, "opcounters.command", $opcounters_command) ;

$asserts_regular = $server_status['asserts']['regular'] ;
write_to_data_lines($zabbix_name, "asserts.regular", $asserts_regular) ;

$asserts_warning = $server_status['asserts']['warning'] ;
write_to_data_lines($zabbix_name, "asserts.warning", $asserts_warning) ;

$asserts_msg = $server_status['asserts']['msg'] ;
write_to_data_lines($zabbix_name, "asserts.msg", $asserts_msg) ;

$asserts_user = $server_status['asserts']['user'] ;
write_to_data_lines($zabbix_name, "asserts.user", $asserts_user) ;

$asserts_rollovers = $server_status['asserts']['rollovers'] ;
write_to_data_lines($zabbix_name, "asserts.rollovers", $asserts_rollovers) ;

$network_inbound_traffic_mb = ($server_status['network']['bytesIn'])/(1024*1024) ;
write_to_data_lines($zabbix_name, "network.inbound.traffic_mb", $network_inbound_traffic_mb) ;

$network_outbound_traffic_mb = ($server_status['network']['bytesOut'])/(1024*1024) ;
write_to_data_lines($zabbix_name, "network.outbound.traffic_mb", $network_outbound_traffic_mb) ;

$network_requests = $server_status['network']['numRequests'] ;
write_to_data_lines($zabbix_name, "network.requests", $network_requests) ;

$write_backs_queued = $server_status['writeBacksQueued'] ;
if ($write_backs_queued) {
  write_to_data_lines($zabbix_name, "write_backs_queued", "Yes") ;
} else {
  write_to_data_lines($zabbix_name, "write_backs_queued", "No") ;
}
/*
$logging_commits = $server_status['dur']['commits'] ;
write_to_data_lines($zabbix_name, "logging.commits", $logging_commits) ;

$logging_journal_writes_mb = $server_status['dur']['journaledMB'] ;
write_to_data_lines($zabbix_name, "logging.journal_writes_mb", $logging_journal_writes_mb) ;

$logging_datafile_writes_mb = $server_status['dur']['writeToDataFilesMB'] ;
write_to_data_lines($zabbix_name, "logging.datafile_writes_mb", $logging_datafile_writes_mb) ;

$logging_commits_in_writelock = $server_status['dur']['commitsInWriteLock'] ;
write_to_data_lines($zabbix_name, "logging.commits_in_writelock", $logging_commits_in_writelock) ;

$logging_early_commits = $server_status['dur']['earlyCommits'] ;
write_to_data_lines($zabbix_name, "logging.early_commits", $logging_early_commits) ;

$logging_log_buffer_prep_time_ms = $server_status['dur']['timeMs']['prepLogBuffer'] ;
write_to_data_lines($zabbix_name, "logging.log_buffer_prep_time_ms", $logging_log_buffer_prep_time_ms) ;

$logging_journal_write_time_ms = $server_status['dur']['timeMs']['writeToJournal'] ;
write_to_data_lines($zabbix_name, "logging.journal_write_time_ms", $logging_journal_write_time_ms) ;

$logging_datafile_write_time_ms = $server_status['dur']['timeMs']['writeToDataFiles'] ;
write_to_data_lines($zabbix_name, "logging.datafile_write_time_ms", $logging_datafile_write_time_ms) ;
*/
//-----------------------------
// Get DB list and cumulative DB info
//-----------------------------
$db_list = $mongo_connection->listDBs() ;

$db_count = count($db_list) ;
write_to_data_lines($zabbix_name, "db.count", $db_count) ;

$totalSize = round(($db_list['totalSize'])/(1024*1024), 2) ;
write_to_data_lines($zabbix_name, "total.size", $totalSize) ;

$sharded_db_count = 0 ;
$total_collection_count = 0 ;
$total_object_count = 0 ;
$total_index_count = 0 ;
$total_index_size = 0.0 ;

$is_sharded = 'No' ;

$db_info_array = array() ;
$db_info_collections = array() ;
$db_info_objects = array() ;
$db_info_indexes = array() ;
$db_info_avgObjSize = array() ;
$db_info_dataSize = array() ;
$db_info_indexSize = array() ;
$db_info_storageSize = array() ;
$db_info_numExtents_array = array() ;
$db_info_fileSize = array() ;


foreach($db_list['databases'] as $db) {
    if(isset($db['shards'])) {
        $is_sharded = 'Yes' ;
    }
    else {
       // Do nothing !
    }

    $mongo_db_handle = $mongo_connection->selectDB($db['name']) ;
    $db_stats = $mongo_db_handle->command(array('dbStats'=>1)) ;

    $execute_status = $db_stats['ok'] ;

    if ($execute_status == 0) {
        write_to_log("Error in executing $command for database ".$db['name']) ;
        exit ;
    }

    $total_collection_count += $db_stats['collections'] ;
    $total_object_count += $db_stats['objects'] ;
    $total_index_count += $db_stats['indexes'] ;
    $total_index_size += $db_stats['indexSize'] ;

    $db_info_array[] = array("{#DBNAME}" => $db['name']) ;
    $db_info_collections[$db['name']] = $db_stats['collections'] ;
    $db_info_objects[$db['name']] = $db_stats['objects'] ;
    $db_info_indexes[$db['name']] = $db_stats['indexes'] ;
    $db_info_avgObjSize[$db['name']] = $db_stats['avgObjSize'] ;
    $db_info_dataSize[$db['name']] = $db_stats['dataSize'] ;
    $db_info_indexSize[$db['name']] = $db_stats['indexSize'] ;
    $db_info_storageSize[$db['name']] = $db_stats['storageSize'] ;
    $db_info_numExtents_array[$db['name']] = $db_stats['numExtents'] ;
    //$db_info_fileSize[$db['name']] = $db_stats['fileSize'] ;
}

write_to_data_lines($zabbix_name, "db.discovery", str_replace("\"", "\\\"", json_encode(array("data" => $db_info_array)))) ;

write_to_data_lines($zabbix_name, "is_sharded", $is_sharded) ;

write_to_data_lines($zabbix_name, "total.collection.count", $total_collection_count) ;

write_to_data_lines($zabbix_name, "total.object.count", $total_object_count) ;

write_to_data_lines($zabbix_name, "total.index.count", $total_index_count) ;

$total_index_size = round($total_index_size/(1024*1024), 2) ;
write_to_data_lines($zabbix_name, "total.index.size", $total_index_size) ;

foreach($db_info_collections as $name => $dummy) {
    write_to_data_lines($zabbix_name, "db.collections[" . $name . "]", $db_info_collections[$name]) ;
    write_to_data_lines($zabbix_name, "db.objects[" . $name . "]", $db_info_objects[$name]) ;
    write_to_data_lines($zabbix_name, "db.indexes[" . $name . "]", $db_info_indexes[$name]) ;
    write_to_data_lines($zabbix_name, "db.avgObjSize[" . $name . "]", $db_info_avgObjSize[$name]) ;
    write_to_data_lines($zabbix_name, "db.dataSize[" . $name . "]", $db_info_dataSize[$name]) ;
    write_to_data_lines($zabbix_name, "db.indexSize[" . $name . "]", $db_info_indexSize[$name]) ;
    write_to_data_lines($zabbix_name, "db.storageSize[" . $name . "]", $db_info_storageSize[$name]) ;
    write_to_data_lines($zabbix_name, "db.numExtents[" . $name . "]", $db_info_numExtents_array[$name]) ;
//    write_to_data_lines($zabbix_name, "db.fileSize[" . $name . "]", $db_info_fileSize[$name]) ;
}


//-----------------------------
// Check for replication / replicaSets
//-----------------------------
if ($is_sharded == 'No') {
   $mongo_db_handle = $mongo_connection->selectDB('admin') ;
   $rs_status = $mongo_db_handle->command(array('replSetGetStatus'=>1)) ;

   if (!($rs_status['ok'])) {
       write_to_data_lines($zabbix_name, "is_replica_set",  "No") ;
   }
   else {
       write_to_data_lines($zabbix_name, "is_replica_set", "Yes") ;
       write_to_data_lines($zabbix_name, "replica_set_name", $rs_status['set']) ;
       write_to_data_lines($zabbix_name, "replica_set_member_count", count($rs_status['members']) )  ;

       $repl_set_member_names = '' ;
       foreach ($rs_status['members'] as $repl_set_member) {
           $repl_set_member_names .= 'host#' . $repl_set_member['_id'] . ' = ' . $repl_set_member['name'] . ' || ' ;
       }
       write_to_data_lines($zabbix_name, "replica_set_hosts", $repl_set_member_names)  ;

       $local_mongo_db_handle = $mongo_connection->selectDB('local') ;
       $col_name = 'oplog.rs' ;
       $mongo_collection = $local_mongo_db_handle->$col_name ;
       $oplog_rs_count = $mongo_collection->count() ;
       write_to_data_lines($zabbix_name, "oplog.rs_count", $oplog_rs_count)  ;

       //$rs_status = $mongo_db_handle->execute("$command") ;
       $repl_member_attention_state_count = 0 ;
       $repl_member_attenntion_state_info = '' ;

       foreach($rs_status['members'] as $member) {
           $member_state = $member['state'] ;

            $host = explode(':', $member['name']);
            $hostname = $host[0];

            if ($member_state == 1) {
                $master_optime = $member['optime'];
            }

            $fqdn = explode('.', $mongodb_host);
            $mongodb_host_simple = $fqdn[0];

            if (!in_array($hostname, array($mongodb_host_simple, $mongodb_host))) {
                continue;
            }

            $mongo_host_optime = $member['optime'];
            $seconds = $master_optime->sec - $mongo_host_optime->sec;

            if ($seconds < 0) {
                $seconds = 0;
            }

            write_to_data_lines($zabbix_name, "repl_member_replication_lag_sec", $seconds) ;

           if($member_state == 0 or $member_state == 3 or $member_state == 4 or $member_state == 5 or $member_state == 6 or $member_state == 8) {
               // 0 = Starting up, phase 1
               // 1 = primary
               // 2 = secondary
               // 3 = recovering
               // 4 = fatal error
               // 5 = starting up, phase 2
               // 6 = unknown state
               // 7 = arbiter
       echo "aaa\n" ;
               // 8 = down
               $repl_member_attention_state_count++ ;
               switch ($member_state) {
                   case 0: $member_state = 'starting up, phase 1' ;
                           break ;
                   case 3: $member_state = 'recovering' ;
                           break ;
                   case 4: $member_state = 'fatal error' ;
                           break ;
                   case 5: $member_state = 'starting up, phase 2' ;
                           break ;
                   case 6: $member_state = 'unknown' ;
                           break ;
                   case 8: $member_state = 'down' ;
                           break ;
                   default: $member_state = 'unknown' ;
                           break ;
               }
               $repl_member_attention_state_info .= $member['name'] . ' is in state ' . $member_state . ' ||' ;
           }
       }
       write_to_data_lines($zabbix_name, "repl_member_attention_state_count", $repl_member_attention_state_count) ;
       write_to_data_lines($zabbix_name, "repl_member_attention_state_info", ($repl_member_attention_state_count > 0 ? $repl_member_attention_state_info : 'empty') ) ;
   }

}

//-----------------------------
// Check for sharding
//-----------------------------
if ($is_sharded == 'Yes') {
    $mongo_db_handle = $mongo_connection->selectDB('config') ;

    $mongo_collection = $mongo_db_handle->chunks ;
    $shard_info = $mongo_collection->count() ;
    write_to_data_lines($zabbix_name, "shard_chunk_count", $shard_info) ;

    $mongo_collection = $mongo_db_handle->collections ;
    $shard_info = $mongo_collection->count() ;
    write_to_data_lines($zabbix_name, "sharded_collections_count", $shard_info) ;

    $collection = $mongo_connection->selectDB('config')->selectCollection('collections') ;
    $cursor = $collection->find() ;
    $collection_array = iterator_to_array($cursor) ;
    $collection_info = '' ;
    foreach ($collection_array as $shard) {
        $collection_info .= $shard['_id'] . ' || ' ;
    }
    write_to_data_lines($zabbix_name, "sharded_collection_info", $collection_info) ;


    $command = "db.shards.count" ;
    $mongo_collection = $mongo_db_handle->shards ;
    $shard_info = $mongo_collection->count() ;
    write_to_data_lines($zabbix_name, "shard_count", $shard_info) ;

    $collection = $mongo_connection->selectDB('config')->selectCollection('shards') ;
    $cursor = $collection->find() ;
    $shards_array = iterator_to_array($cursor) ;
    $shard_info = '' ;
    foreach ($shards_array as $shard) {
        $shard_info .= $shard['_id'] . ' = ' . $shard['host'] . ' || ' ;
    }
    write_to_data_lines($zabbix_name, "shard_info", $shard_info) ;

    $collection = $mongo_connection->selectDB('config')->selectCollection('databases') ;
    $cursor = $collection->find() ;
    $db_array = iterator_to_array($cursor) ;
    $db_info = '' ;
    foreach ($db_array as $db) {
        if( $db['partitioned'] ) {
            $partitioned = 'yes' ;
        }
        else {
            $partitioned = 'no' ;
        }
        $db_info .= $db['_id'] . ' : ' . 'partitioned = ' . $partitioned . ', primary = ' . $db['primary'] . ' || ' ;
    }
    write_to_data_lines($zabbix_name, "db_info", $db_info) ;


}

//-------------------------------------------------------------------------//


// Get data collection end time (we will use this to compute the total data collection time)
$end_time = time() ;
$data_collection_time = $end_time - $start_time ;
write_to_data_lines($zabbix_name, "plugin.data_collection_time", $data_collection_time) ;

write_to_data_lines($zabbix_name, "plugin.version", $command_version) ;
write_to_data_lines($zabbix_name, "plugin.checksum", $md5_checksum_string) ;

// For DEBUG
if ($debug_mode) {
    $data_file_name = "/tmp/${command_name}_${file_base_name}.data" ;
    file_put_contents($data_file_name, implode("\n", $data_lines) . "\n") ;
}

$descriptorspec = array(
    0 => array("pipe", "r"),  // stdin
    1 => array("pipe", "w"),  // stdout
    2 => array("pipe", "w")   // stderr
) ;
$process = proc_open("zabbix_sender -vv -z $zabbix_server -p $zabbix_server_port -i - 2>&1", $descriptorspec, $pipes) ;

if (is_resource($process)) {
    fwrite($pipes[0], implode("\n", $data_lines)) ;
    fclose($pipes[0]) ;

    while($s = fgets($pipes[1], 1024)) {
        write_to_log("O: " . trim($s)) ;
    }
    fclose($pipes[1]);

    while($s= fgets($pipes[2], 1024)) {
        write_to_log("E: " . trim($s)) ;
    }
    fclose($pipes[2]) ;
}

exit ;

?>

