<?php
#!/data/web/neurodb/software/bin/php
/**
 * @version $Id: derive_timepoint_flags.php,v 3.17 2006/06/20 15:12:29 dario Exp $
 * derives exclusion flags and stores them into parameter_exclusion_session table
 * @package timepoint_flag
 */

// define a config file to use
$configFile = "../project/config.xml";

set_include_path(get_include_path().":../php/libraries:");
require_once "NDB_Client.class.inc";
$client = new NDB_Client();
$client->makeCommandLine();
$client->initialize($configFile);

$tests = $DB->pselect("SELECT Test_name FROM test_names WHERE Test_name NOT LIKE '%_proband' AND Test_name NOT LIKE 'EARLI%'", array()) ;
/* ************************************************************************* */
// Determine instruments where administration < DoB, or sessions where Visit < DoB
/* ************************************************************************* */
print "SESSIONS WITH ADMINISTRATION BEFORE DOB\n";
print "---------------------------------------\n";
$queries = array("SELECT c.PSCID, c.CandID, s.Visit_label, 'Date_visit' FROM candidate c LEFT JOIN session s USING (CandID) WHERE s.Date_visit < c.DoB AND c.CenterID <> 1 AND COALESCE(s.Visit, 'NotFailure') <> 'Failure'");

foreach($tests as $row) {
    $test = $row['Test_name'];
    $queries[] = "SELECT c.PSCID, c.CandID, s.Visit_label, '$test' FROM candidate c LEFT JOIN session s USING (CandID) LEFT JOIN flag f ON (f.SessionID=s.ID) LEFT JOIN $test t USING (CommentID) WHERE f.Test_name=" . $DB->quote($test)  . " AND t.Date_taken < c.DoB AND c.CenterID <> 1 AND f.Data_entry='Complete' AND COALESCE(s.Visit, 'NotFailure') <> 'Failure'";
}
$query = implode($queries, " UNION ");
//print "$query\n";
$bad_entries = $DB->pselect($query, array());
foreach($bad_entries as $row) {
    print implode($row, "\t");
    print "\n";
}

/* ************************************************************************* */
// Determine sessions where Date_taken of visit n > Date taken of visit n+1
/* ************************************************************************* */
print "\n\nSESSIONS WITH VISIT N AFTER VISIT N+1\n";
print "-------------------------------------\n";
foreach($tests as $test_row) {
    $test = $test_row['Test_name'];
    $instrument_query = "SELECT c.PSCID, c.CandID, t.Date_taken, s.Visit_label, f.CommentID FROM candidate c LEFT JOIN session s USING (CandID) LEFT JOIN flag f ON (f.SessionID=s.ID) JOIN $test t USING (CommentID) WHERE f.Test_name=" . $DB->quote($test)  . " AND f.CommentID NOT LIKE 'DDE%' AND s.Active='Y' AND c.Active='Y' AND t.Date_taken IS NOT NULL  AND f.Data_entry='Complete' AND COALESCE(s.Visit, 'NotFailure') <> 'Failure' ORDER BY PSCID, Visit_label";
    $instrument_data = $DB->pselect($instrument_query, array());
//    print_r($instrument_data);
    $LastCandidate = '';
    $LastDateTaken = 0;
    $LastCommentID = '';
    foreach($instrument_data as $row) {
        if($row['PSCID'] != $LastCandidate) {
            $LastCandidate = $row['PSCID'];
            $LastDateTaken = $row['Date_taken'];
            $LastVisit = $row['Visit_label'];
            $LastCommentID= $row['CommentID'];
            continue;
        }
        if($LastDateTaken > $row['Date_taken']) {
            $disp = array($row['PSCID'], $row['CandID'], $row['Visit_label'], $test, $LastVisit . ' > ' . $row['Visit_label']);
            print implode($disp, "\t");
            print "\n";
        }
        $LastCandidate = $row['PSCID'];
        $LastDateTaken = $row['Date_taken'];
        $LastVisit = $row['Visit_label'];
        $LastCommentID= $row['CommentID'];
        
    }
}
?>
