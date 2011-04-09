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

$options = getopt("Dh:p:z:u:x:") ;
$command_name = basename($argv[0]) ;
$command_version = "0.3" ;

// Get data collection start time (we will use this to compute the total data collection time)
$start_time = time() ;

// At a minimum, we need to get the Zabbix hostname. If not, display usage message.
if (empty($options) or empty($options['z']) ) {
    echo "
$command_name Version $command_version
Usage : $command_name [-D] [-h <mongoDB Server Host>] [-p <mongoDB Port>] [-u <username>] [-x <password>] -z <Zabbix_Name>
where
   -D = Run in detail/debug mode
   -h = Hostname or IP address of server running mongoDBB
   -p = Port number on which to connect to the mongod or mongos process
   -z = Name (hostname) of mongoDB instance or cluster in the Zabbix UI
   -u = User name for database authentication
   -x = Password for database authentication
"  ;

exit ;
}

//-------------------------------------------------------------------------//
// Setup log file, data file and zabbix hostname.
//-------------------------------------------------------------------------//
$zabbix_name = $options['z'] ;

$debug_mode = isset($options['D']) ;

// Log file : contains execution and diagnostic information
$log_file_name = "/tmp/${command_name}_${zabbix_name}.log" ;
$log_file_handle = fopen($log_file_name, 'w') ;
$log_file_data = array() ;

// Data file : contains data to send to Zabbix and corresponds to data items in template
$data_file_name = "/tmp/${command_name}_${zabbix_name}.data" ;
$data_file_handle = fopen($data_file_name, 'w') ;

if (!($data_file_handle)) {
    write_to_log_file("$command_name:There was an error in opening data file $data_file_name\n") ;
    exit ;
}

$md5_checksum_string = md5_file($argv[0]) ;

if ($debug_mode) {
    write_to_log_file("$command_name version $command_version\n") ;
}
//-------------------------------------------------------------------------//


//-------------------------------------------------------------------------//
function write_to_log_file($output_line)
//-------------------------------------------------------------------------//
{
    global $command_name ;
    global $log_file_handle ;
    fwrite($log_file_handle, "$output_line\n") ;
}
//-------------------------------------------------------------------------//



//-------------------------------------------------------------------------//
function debug_output($output_line)
//-------------------------------------------------------------------------//
{
    global $debug_mode ;
    global $command_name ;
    global $log_file_handle ;
    if ($debug_mode) {
        fwrite($log_file_handle, "$output_line\n") ;
    }
}
//-------------------------------------------------------------------------//


//-------------------------------------------------------------------------//
function write_to_data_file($output_line)
//-------------------------------------------------------------------------//
{
    global $data_file_handle ;
    fwrite($data_file_handle, "$output_line\n") ;
}
//-------------------------------------------------------------------------//


//-------------------------------------------------------------------------//
// Now starts the heart of mongoDB monitoring !!
//-------------------------------------------------------------------------//

//-----------------------------
// Setup connection to mongoDB
//-----------------------------
$mongodb_host = empty($options['h']) ? Mongo::DEFAULT_HOST : $options['h'] ;
$mongodb_port = empty($options['p']) ? Mongo::DEFAULT_PORT : $options['p']  ;

if ((!empty($options['u'])) && (!empty($options['x']))) {
    $connect_string = $options['u'] . ':' . $options['x'] . '@' . $mongodb_host . ':' . $mongodb_port  ;
}
else {
    $connect_string = $mongodb_host . ':' . $mongodb_port ;
}

$mongo_connection = new Mongo("mongodb://$connect_string") ;

if (is_null($mongo_connection)) {
    write_to_log_file("$command_name:Error in connection to mongoDB using connect string $connect_string") ;
    exit ;
}
else {
    write_to_log_file("$command_name:Successfully connected to mongoDB using connect string $connect_string") ;
}


//-----------------------------
// Get server statistics
//-----------------------------
$mongo_db_handle = $mongo_connection->selectDB("admin") ;

$command = "db.serverStatus()" ;
$server_status = $mongo_db_handle->execute($command) ;

if (isset($server_status['retval']['ok'])) {
    $execute_status = $server_status['retval']['ok'] ;
}
else {
    $execute_status = 0 ;
}

if (!isset($server_status['retval']['ok'])) {
    write_to_log_file("$command_name:Error in executing $command. Probably connection attempted to non-master node/instance") ;
    exit ;
}
    
$mongo_version = $server_status['retval']['version'] ;
write_to_data_file("$zabbix_name mongodb_version $mongo_version") ;

$uptime = $server_status['retval']['uptime'] ;
write_to_data_file("$zabbix_name uptime $uptime") ;

//$globalLock_totalTime = $server_status['retval']['globalLock']['totalTime'] ;
//write_to_data_file("$zabbix_name globalLock_totalTime $globalLock_totalTime") ;

$globalLock_lockTime = $server_status['retval']['globalLock']['lockTime'] ;
write_to_data_file("$zabbix_name globalLock_lockTime $globalLock_lockTime") ;

$globalLock_currentQueue_total = $server_status['retval']['globalLock']['currentQueue']['total'] ;
write_to_data_file("$zabbix_name globalLock_currentQueue_total $globalLock_currentQueue_total") ;

$globalLock_currentQueue_readers = $server_status['retval']['globalLock']['currentQueue']['readers'] ;
write_to_data_file("$zabbix_name globalLock_currentQueue_readers $globalLock_currentQueue_readers") ;

$globalLock_currentQueue_writers = $server_status['retval']['globalLock']['currentQueue']['writers'] ;
write_to_data_file("$zabbix_name globalLock_currentQueue_writers $globalLock_currentQueue_writers") ;

$mem_bits = $server_status['retval']['mem']['bits'] ;
write_to_data_file("$zabbix_name mem_bits $mem_bits") ;

$mem_resident = $server_status['retval']['mem']['resident'] ;
write_to_data_file("$zabbix_name mem_resident $mem_resident") ;

$mem_virtual = $server_status['retval']['mem']['virtual'] ;
write_to_data_file("$zabbix_name mem_virtual $mem_virtual") ;

$connections_current = $server_status['retval']['connections']['current'] ;
write_to_data_file("$zabbix_name connections_current $connections_current") ;

$connections_available = $server_status['retval']['connections']['available'] ;
write_to_data_file("$zabbix_name connections_available $connections_available") ;

$extra_info_heap_usage = round(($server_status['retval']['extra_info']['heap_usage_bytes'])/(1024*124), 2) ;
write_to_data_file("$zabbix_name extra_info_heap_usage $extra_info_heap_usage") ;

$extra_info_page_faults = $server_status['retval']['extra_info']['page_faults'] ;
write_to_data_file("$zabbix_name extra_info_page_faults $extra_info_page_faults") ;

$indexCounters_btree_accesses = $server_status['retval']['indexCounters']['btree']['accesses'] ;
write_to_data_file("$zabbix_name indexCounters_btree_accesses $indexCounters_btree_accesses") ;

$indexCounters_btree_hits = $server_status['retval']['indexCounters']['btree']['hits'] ;
write_to_data_file("$zabbix_name indexCounters_btree_hits $indexCounters_btree_hits") ;

$indexCounters_btree_misses = $server_status['retval']['indexCounters']['btree']['misses'] ;
write_to_data_file("$zabbix_name indexCounters_btree_misses $indexCounters_btree_misses") ;

$indexCounters_btree_resets = $server_status['retval']['indexCounters']['btree']['resets'] ;
write_to_data_file("$zabbix_name indexCounters_btree_resets $indexCounters_btree_resets") ;

$indexCounters_btree_missRatio = $server_status['retval']['indexCounters']['btree']['missRatio'] ;
write_to_data_file("$zabbix_name indexCounters_btree_missRatio $indexCounters_btree_missRatio") ;

$backgroundFlushing_flushes = $server_status['retval']['backgroundFlushing']['flushes'] ;
write_to_data_file("$zabbix_name backgroundFlushing_flushes $backgroundFlushing_flushes") ;

$backgroundFlushing_total_ms = $server_status['retval']['backgroundFlushing']['total_ms'] ;
write_to_data_file("$zabbix_name backgroundFlushing_total_ms $backgroundFlushing_total_ms") ;

$backgroundFlushing_average_ms = $server_status['retval']['backgroundFlushing']['average_ms'] ;
write_to_data_file("$zabbix_name backgroundFlushing_average_ms $backgroundFlushing_average_ms") ;

$backgroundFlushing_last_ms = $server_status['retval']['backgroundFlushing']['last_ms'] ;
write_to_data_file("$zabbix_name backgroundFlushing_last_ms $backgroundFlushing_last_ms") ;

$cursors_totalOpen = $server_status['retval']['cursors']['totalOpen'] ;
write_to_data_file("$zabbix_name cursors_totalOpen $cursors_totalOpen") ;

$cursors_clientCursors_size = $server_status['retval']['cursors']['clientCursors_size'] ;
write_to_data_file("$zabbix_name cursors_clientCursors_size $cursors_clientCursors_size") ;

$cursors_timedOut = $server_status['retval']['cursors']['timedOut'] ;
write_to_data_file("$zabbix_name cursors_timedOut $cursors_timedOut") ;

$opcounters_insert = $server_status['retval']['opcounters']['insert'] ;
write_to_data_file("$zabbix_name opcounters_insert $opcounters_insert") ;

$opcounters_query = $server_status['retval']['opcounters']['query'] ;
write_to_data_file("$zabbix_name opcounters_query $opcounters_query") ;

$opcounters_update = $server_status['retval']['opcounters']['update'] ;
write_to_data_file("$zabbix_name opcounters_update $opcounters_update") ;

$opcounters_delete = $server_status['retval']['opcounters']['delete'] ;
write_to_data_file("$zabbix_name opcounters_delete $opcounters_delete") ;

$opcounters_getmore = $server_status['retval']['opcounters']['getmore'] ;
write_to_data_file("$zabbix_name opcounters_getmore $opcounters_getmore") ;

$opcounters_command = $server_status['retval']['opcounters']['command'] ;
write_to_data_file("$zabbix_name opcounters_command $opcounters_command") ;

$asserts_regular = $server_status['retval']['asserts']['regular'] ;
write_to_data_file("$zabbix_name asserts_regular $asserts_regular") ;

$asserts_warning = $server_status['retval']['asserts']['warning'] ;
write_to_data_file("$zabbix_name asserts_warning $asserts_warning") ;

$asserts_msg = $server_status['retval']['asserts']['msg'] ;
write_to_data_file("$zabbix_name asserts_msg $asserts_msg") ;

$asserts_user = $server_status['retval']['asserts']['user'] ;
write_to_data_file("$zabbix_name asserts_user $asserts_user") ;

$asserts_rollovers = $server_status['retval']['asserts']['rollovers'] ;
write_to_data_file("$zabbix_name asserts_rollovers $asserts_rollovers") ;


//-----------------------------
// Get DB list and cumulative DB info
//-----------------------------
$db_list = $mongo_connection->listDBs() ;

$db_count = count($db_list) ;
write_to_data_file("$zabbix_name db_count $db_count") ;

$totalSize = round(($db_list['totalSize'])/(1024*1024), 2) ;
write_to_data_file("$zabbix_name totalSize $totalSize") ;

$sharded_db_count = 0 ;
$total_collection_count = 0 ;
$total_object_count = 0 ;
$total_index_count = 0 ;

$is_sharded = 'No' ;

$db_info_array = '' ;
$db_info_collections = '' ;
$db_info_objects = '' ;
$db_info_indexes = '' ;
$db_info_avgObjSize = '' ;
$db_info_dataSize = '' ;
$db_info_indexSize = '' ;
$db_info_storageSize = '' ;
$db_info_numExtents_array = '' ;
$db_info_fileSize = '' ;

foreach($db_list['databases'] as $db) {
    if(isset($db['shards'])) {
        $is_sharded = 'Yes' ;
    }
    else {
       // Do nothing !
    }
    $mongo_db_handle = $mongo_connection->selectDB($db['name']) ;
    $command = "db.stats()" ;
    $db_stats = $mongo_db_handle->execute("$command") ;

    $execute_status = $db_stats['retval']['ok'] ;

    if ($execute_status == 0) {
        write_to_log_file("$command_name:Error in executing $command for database".$db['name']) ;
        exit ;
    }

    $total_collection_count += $db_stats['retval']['collections'] ;
    $total_object_count += $db_stats['retval']['objects'] ;
    $total_index_count += $db_stats['retval']['indexes'] ;

    $db_info_array[$db['name']] .= ' collections=' . $db_stats['retval']['collections'] .
                                  ', objects=' . $db_stats['retval']['objects'] .
                                  ', indexes=' . $db_stats['retval']['indexes']  .
                                  ', avgObjSize=' . $db_stats['retval']['avgObjSize']  .
                                  ', dataSize=' . $db_stats['retval']['dataSize']  .
                                  ', indexSize=' . $db_stats['retval']['indexSize']  .
                                  ', storageSize=' . $db_stats['retval']['storageSize']  .
                                  ', numExtents=' . $db_stats['retval']['numExtents']  .
                                  ', fileSize=' . $db_stats['retval']['fileSize']  ;

    $db_info_collections .= $db['name'] . '=' . $db_stats['retval']['collections'] . ' || ' ;
    $db_info_objects .= $db['name'] . '=' . $db_stats['retval']['objects'] . ' || ' ;
    $db_info_indexes .= $db['name'] . '=' . $db_stats['retval']['indexes'] . ' || ' ;
    $db_info_avgObjSize .= $db['name'] . '=' . $db_stats['retval']['avgObjSize'] . ' || ';
    $db_info_dataSize .= $db['name'] . '=' . $db_stats['retval']['dataSize'] . ' || ';
    $db_info_indexSize .= $db['name'] . '=' . $db_stats['retval']['indexSize'] . ' || ';
    $db_info_storageSize .= $db['name'] . '=' . $db_stats['retval']['storageSize'] . ' || ';
    $db_info_numExtents_array .= $db['name'] . '=' . $db_stats['retval']['numExtents'] . ' || ';
    $db_info_fileSize .= $db['name'] . '=' . $db_stats['retval']['fileSize'] . ' || ';
}

foreach($db_info_array as $key=>$value) {
   $db_info .= $key . ':' . $value . ' || ' ;
}

write_to_data_file("$zabbix_name database_info $db_info") ;

write_to_data_file("$zabbix_name is_sharded $is_sharded") ;

write_to_data_file("$zabbix_name total_collection_count $total_collection_count") ;

write_to_data_file("$zabbix_name total_object_count $total_object_count") ;

write_to_data_file("$zabbix_name total_index_count $total_index_count") ;

write_to_data_file("$zabbix_name db_collections $db_info_collections") ;
write_to_data_file("$zabbix_name db_objects $db_info_objects") ;
write_to_data_file("$zabbix_name db_indexes $db_info_indexes") ;
write_to_data_file("$zabbix_name db_avgObjSize $db_info_avgObjSize") ;
write_to_data_file("$zabbix_name db_dataSize $db_info_dataSize") ;
write_to_data_file("$zabbix_name db_indexSize $db_info_indexSize") ;
write_to_data_file("$zabbix_name db_storageSize $db_info_storageSize") ;
write_to_data_file("$zabbix_name db_numExtents $db_info_numExtents_array") ;
write_to_data_file("$zabbix_name db_fileSize $db_info_fileSize") ;


//-----------------------------
// Check for replication / replicaSets
//-----------------------------
$mongo_db_handle = $mongo_connection->selectDB('local') ;
$command = "rs.conf()" ;
$rs_status = $mongo_db_handle->execute("$command") ;


if (!($rs_status['retval'])) {
    write_to_data_file("$zabbix_name isReplicaSet No") ;
}
else {
    write_to_data_file("$zabbix_name is_replica_set Yes") ;
    write_to_data_file("$zabbix_name replica_set_name ".$rs_status['retval']['_id']) ;
    write_to_data_file("$zabbix_name replica_set_member_count " . count($rs_status['retval']['members']) )  ;

    foreach ($rs_status['retval']['members'] as $repl_set_member) {
        $repl_set_member_names .= 'host#' . $repl_set_member['_id'] . ' = ' . $repl_set_member['host'] . ' || ' ;
    }
    write_to_data_file("$zabbix_name replica_set_hosts " . $repl_set_member_names)  ;

    $command = "db.oplog.rs.count()" ;
    $rs_status = $mongo_db_handle->execute("$command") ;
    write_to_data_file("$zabbix_name oplog.rs_count " . $rs_status['retval'])  ;

    $command = "rs.status()" ;
    $rs_status = $mongo_db_handle->execute("$command") ;
    $repl_member_attention_state_count = 0 ;
    $repl_member_attenntion_state_info = '' ;
    foreach($rs_status['retval']['members'] as $member) {
        $member_state = $member['state'] ;
        if($member_state == 0 or $member_state == 3 or $member_state == 4 or $member_state == 5 or $member_state == 6 or $member_state == 8) {
            // 0 = Starting up, phase 1
            // 1 = primary
            // 2 = secondary
            // 3 = recovering
            // 4 = fatal error
            // 5 = starting up, phase 2
            // 6 = unknown state
            // 7 = arbiter
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
            $repl_member_attenntion_state_info .= $member['name'] . ' is in state ' . $member_state . ' ||' ;
        }
    }
    write_to_data_file("$zabbix_name repl_member_attention_state_count " . $repl_member_attention_state_count) ;
    write_to_data_file("$zabbix_name repl_member_attention_state_info " . $repl_member_attention_state_info) ;
}    


//-----------------------------
// Check for sharding
//-----------------------------
if ($is_sharded == 'Yes') {
    $mongo_db_handle = $mongo_connection->selectDB('config') ;

    $command = "db.chunks.count()" ;
    $shard_info = $mongo_db_handle->execute("$command") ;
    write_to_data_file("$zabbix_name shard_chunk_count " . $shard_info['retval']) ;

    $command = "db.collections.count()" ;
    $shard_info = $mongo_db_handle->execute("$command") ;
    write_to_data_file("$zabbix_name sharded_collections_count " . $shard_info['retval']) ;

    $collection = $mongo_connection->selectDB('config')->selectCollection('collections') ;
    $cursor = $collection->find() ;
    $collection_array = iterator_to_array($cursor) ;
    $collection_info = '' ;
    foreach ($collection_array as $shard) {
        $collection_info .= $shard['_id'] . ' || ' ;
    }
    write_to_data_file("$zabbix_name sharded_collection_info " . $collection_info) ;


    $command = "db.shards.count()" ;
    $shard_info = $mongo_db_handle->execute("$command") ;
    write_to_data_file("$zabbix_name shard_count " . $shard_info['retval']) ;

    $collection = $mongo_connection->selectDB('config')->selectCollection('shards') ;
    $cursor = $collection->find() ;
    $shards_array = iterator_to_array($cursor) ;
    $shard_info = '' ;
    foreach ($shards_array as $shard) {
        $shard_info .= $shard['_id'] . ' = ' . $shard['host'] . ' || ' ;
    }
    write_to_data_file("$zabbix_name shard_info " . $shard_info) ;

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
    write_to_data_file("$zabbix_name db_info " . $db_info) ;


}

//-------------------------------------------------------------------------//


// Get data collection end time (we will use this to compute the total data collection time)
$end_time = time() ;
$data_collection_time = $end_time - $start_time ;
write_to_data_file("$zabbix_name mongoDB_plugin_data_collection_time $data_collection_time") ;

write_to_data_file("$zabbix_name mongoDB_plugin_version $command_version") ;
write_to_data_file("$zabbix_name mongoDB_plugin_checksum $md5_checksum_string") ;

fclose($data_file_handle) ;

exec("zabbix_sender -vv -z 127.0.0.1 -i $data_file_name 2>&1", $log_file_data) ;

foreach ($log_file_data as $log_line) {
    write_to_log_file("$log_line\n") ;
}

fclose($log_file_handle) ;

exit ;

?>
