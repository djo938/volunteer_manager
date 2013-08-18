<?php session_start(); 
include 'config.inc.php';
include 'function.inc.php';
require('fpdf.php');

//TODO
    //-interpreter les valeurs html en normale
        //-notament pour les titres d'activite dans la grille horaire des activites
    //titre document avec la liste des utilisateurs, peu etre trop grand

try
{
    $dbh        = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $err)
{
    if($DEBUG)
    {
        echo "Erreur: ".$err;
    }
    
    echo "<BR />Il semblerait qu'un probleme de connexion avec la base de donn&eacute;es ait eu lieu.  Si le probl&egrave;me persiste, contactez l'administrateur du site: webmaster@folkfestivalmarsinne.be</body></html>";
    
    exit();
}

//////////////// COUNT FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function getAllUserExceptAdmin($dbh)
{
    $sql_req = "SELECT count(*) as ucount FROM Users WHERE user_type != 'admin'";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

function getValidatedUserCount($dbh)
{
    $sql_req = "SELECT count(*) as ucount FROM Users WHERE user_type = 'validated'";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

function getNonValidatedUserCount($dbh)
{
    $sql_req = "SELECT count(*) as ucount FROM Users WHERE user_type = 'new' or user_type = 'mail_confirmed'";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

function getRecordedSlotCount($dbh)
{
    $sql_req = "SELECT count(*) as ucount FROM User_Timeslot";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

function getAllSlotCount($dbh)
{
    $sql_req = "SELECT sum(NumberOfPeople) as ucount FROM 	Timeslot";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

function getValidatedUserWithoutSlotCount($dbh)
{
    $sql_req = "SELECT count(*) as ucount FROM Users WHERE `ID_Users` NOT IN (SELECT `ID_Users` FROM User_Timeslot) AND user_type = \"validated\"";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["ucount"];
    }
    
    return 0;
}

//////////////// PDF FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/*function buildPDF($list)
{
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    
    foreach($list as $k=>$v) //Name, Family_name,GSM  
    {
        $pdf->MultiCell(0,10,$v);
    }
     
    $pdf->Output();
    exit();
}
*/

/*function buildSeveralPagePDF($list)
{
    $pdf = new FPDF();
    
    foreach($list as $k=>$v)
    {
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',14);
        
        foreach($v as $ki=>$vi)
        {
            $pdf->MultiCell(0,10,$vi);
        }
    }
    
    $pdf->Output();
    exit();
}
*/

function buildSeveralPagePDF_v2($page_list)
{
    //var_dump($page_list);
    $pdf = new FPDF();
    
    foreach($page_list as $main_title=>$title_list)
    {
         $pdf->AddPage();
         $pdf->SetFont('Arial','B',22);
         $pdf->MultiCell(0,15,$main_title);
         
         foreach($title_list as $title=>$lines)
         {
             $pdf->SetFont('Arial','B',14);
             $pdf->MultiCell(0,7,$title);
             $pdf->SetFont('Arial','',12);
             foreach($lines as $i=>$line)
             {
                 $pdf->MultiCell(0,7,$line);
             }
         }
    }
    
    $pdf->Output();
    exit();
}

function FancyTable($pdf,$header, $data)
{
    // Couleurs, épaisseur du trait et police grasse

    $pdf->SetDrawColor(128,0,0);
    $pdf->SetLineWidth(.3);
    
    $pdf->SetFillColor(255,0,0);
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial','B',7);
    // En-tête
    $header_max_length = 25;
    $cell_size = 35;
    $w               = array(65, 29, 29, 29, 29, 29, 29, 29);
    $text_size_array = array(25, 22, 22, 22, 22, 22, 22, 22);
    $offset_array    = array( 0,  0,  0,  0,  0,  0,  0,  0);
    
    //TODO compute line count
    //find bigger header name
    $line_count = 1;
    for($i=0;$i<count($text_size_array);$i+=1)
    {
        $tmp = (int)(strlen($header[$i]) / $text_size_array[$i]);
        if( (strlen($header[$i]) % $text_size_array[$i]) > 0)
        {
            $tmp += 1;
        }
        if($tmp > $line_count)
        {
            $line_count = $tmp;
        }
    }
    
    //build line
    for($j=0;$j < $line_count;$j+=1)
    {
        for($i=0;$i<count($header);$i++)
        {
            $border = "LR";
            //first line has upper border
            if($j == 0){$border .= "T";}
        
            //last line has lower border
            if($j == ($line_count-1)){$border .= "B";}

            $pdf->Cell($w[$i],7,substr($header[$i],$offset_array[$i], $text_size_array[$i]),$border,0,'C',true);
            $offset_array[$i] += ($text_size_array[$i]);
        }
        $pdf->Ln();
    }
    //$pdf->Ln();
    
    // Restauration des couleurs et de la police
    $pdf->SetFillColor(224,235,255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('','',10);
    // Données
    $fill = false;
    $counter = 0;
    //foreach($data as $row)
    for($k = 0 ; $k < count($data) ;$k+=1)
    {
        $border = 'LR';
        if($counter == 24)
        {
            $border .= "B";
        }
        
        if($k == (count($data)-1))
        {
            $pdf->SetFillColor(255,0,0);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial','',10);
            $fill = true;
        }
        
        $pdf->Cell($w[0],6,$data[$k][0],$border,0,'L',$fill);
        $pdf->Cell($w[1],6,$data[$k][1],$border,0,'L',$fill);
        $pdf->Cell($w[2],6,$data[$k][2],$border,0,'L',$fill);
        $pdf->Cell($w[3],6,$data[$k][3],$border,0,'L',$fill);
        $pdf->Cell($w[4],6,$data[$k][4],$border,0,'L',$fill);
        $pdf->Cell($w[5],6,$data[$k][5],$border,0,'L',$fill);
        $pdf->Cell($w[6],6,$data[$k][6],$border,0,'L',$fill);
        $pdf->Cell($w[7],6,$data[$k][7],$border,0,'L',$fill);
        
        $pdf->Ln();
        $fill = !$fill;
        
        if($counter == 24)
        {
            $pdf->AddPage("L");
            $counter = -1;
            
            if($i < (count($data) -1))
            {
                //$pdf->SetDrawColor(128,0,0);
                $pdf->SetFillColor(255,0,0);
                $pdf->SetTextColor(255);
                $pdf->SetFont('Arial','B',7);
                $offset_array    = array( 0,  0,  0,  0,  0,  0,  0,  0);
                for($j=0;$j < $line_count;$j+=1)
                {
                    for($i=0;$i<count($header);$i++)
                    {
                        $border = "LR";
                        //first line has upper border
                        if($j == 0){$border .= "T";}

                        //last line has lower border
                        if($j == ($line_count-1)){$border .= "B";}

                        $pdf->Cell($w[$i],7,substr($header[$i],$offset_array[$i], $text_size_array[$i]),$border,0,'C',true);
                        $offset_array[$i] += ($text_size_array[$i]);
                    }
                    $pdf->Ln();
                }
                
                $pdf->SetFillColor(224,235,255);
                $pdf->SetTextColor(0);
                $pdf->SetFont('','',10);
            }
        
        }
        $counter += 1;
    }
    // Trait de terminaison
    $pdf->Cell(array_sum($w),0,'','T');
}

function printVolunteerList($list, $title,$gsm = true)
{
    $pdf = new FPDF();
    $pdf->AddPage();
    
    $pdf->SetFont('Arial','B',16);
    $pdf->MultiCell(0,20,$title);
    
    //$pdf->SetFont('Arial','',14);
    
    $fill = false;
    $pdf->SetFillColor(224,235,255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial','',12);
    foreach($list as $k=>$v) //Name, Family_name,GSM  
    {
        //$pdf->MultiCell(0,10,." : ".$v["GSM"]);
        $pdf->Cell(90,7,html_entity_decode($v["Family_name"]." ".$v["Name"],ENT_COMPAT | ENT_HTML401,"cp1252"),1,0,'L',$fill);//,'LR',0,'L',$fill);
        
        if($gsm)
            $pdf->Cell(80,7,$v["GSM"],1,0,'L',$fill);
        else
            $pdf->Cell(90,7,$v["Mail"],1,0,'L',$fill);
            
        $pdf->Ln();
        $fill =  !$fill;
    }
     
    $pdf->Output();
    exit();
}

function build_schedule_activities($sorted_activity_list)
{
    $pdf = new FPDF();
    //$pdf->AddPage();
    //$pdf->SetFont('Arial','B',14);

    foreach($sorted_activity_list as $activity=>$day_list)
    {
        $pdf->AddPage();
        $pdf->SetFont('Arial','BU',14);
        //echo $activity.'<BR />';
        $pdf->cell(0,7,$activity);
        $pdf->ln();
        foreach($day_list as $day=>$slot_list)
        {
            $pdf->SetFont('Arial','U',12);
            //echo "&nbsp;&nbsp;&nbsp;&nbsp;".$day.'<BR />';
            $pdf->cell(0,7,$day);
            $pdf->ln(10);
            
            $pdf->SetFont('Arial','',10);
            $max_user = 0;
            
            $line_to_write = array();//contient les lignes d'utilisateurs
            
            $cell_width = 23;
            $i = 0;
            foreach($slot_list as $time=>$user_list)
            {
                $pdf->Cell($cell_width,7,$time,1);
                if(count($user_list) > $max_user)
                {
                    $max_user = count($user_list);
                }
                
                foreach($user_list as $index=>$user)
                {
                    //ligne      == $index
                    //colonne    == $i
                    
                    if(!array_key_exists($index, $line_to_write))
                    {
                        $line_to_write[$index] = array();
                    }
                    
                    $line_to_write[$index][$i] = $user;
                }

                $i += 1;
            }
            $pdf->ln();
            $pdf->SetFont('Arial','',8);
            $line_counter = 0;
            foreach($line_to_write as $i=>$line)
            {
                if(($line_counter+1) == count($line_to_write))
                {
                    $border = "BLR";
                }
                else
                {
                    $border = "LR";
                }
                
                
                for($i=0;$i<count($slot_list);$i+=1)
                {
                    
                    if(!array_key_exists($i, $line))
                    {
                        $pdf->Cell($cell_width,7," ",$border);
                    }
                    else
                    {
                        $pdf->Cell($cell_width,7,"-".$line[$i],$border);
                    }
                }
                $pdf->ln();
                $line_counter += 1;
            }
            
            $pdf->ln(7);
        }
    }

    $pdf->Output();
    exit();
}

//////////////// LIST FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/*
 *  get all user except admin
 */
function getUserList($dbh)
{
    $sql_req = "SELECT ID_Users, Name, Family_name, user_type, Reliability 
                FROM Users 
                WHERE user_type != 'admin'";
                
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    return $stmt->fetchAll();
}

function getValidatedUserList($dbh)
{
    $sql_req = "SELECT ID_Users, Name, Family_name, user_type, Reliability 
                FROM Users 
                WHERE user_type = 'validated'
                ORDER BY Family_name";
                
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    return $stmt->fetchAll();
}

function getActivityList($dbh)
{
    $sql_req = "SELECT DISTINCT Description FROM Timeslot";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserSchedule($dbh,$id)
{
    $sql_req = "SELECT Timeslot.* 
                FROM Timeslot, User_Timeslot
                WHERE :iduser = User_Timeslot.ID_Users
                AND Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot";
    $stmt = $dbh->prepare($sql_req);  
    $stmt->bindParam(':iduser', $id);                                          
    $stmt->execute();
    return $stmt->fetchAll();
}

function getVolunteerList($dbh,$only_backup=false)
{
        $sql_req = "SELECT *  
                    FROM Users 
                    WHERE user_type = 'validated'";
        
        if($only_backup)
        {
            $sql_req .= " AND backup = 1";
        }
        
        $sql_req .= " ORDER BY Family_name";    
        $stmt = $dbh->prepare($sql_req);                                               
        $stmt->execute();
        
        return $stmt->fetchAll();
}

function getVolunteerWithoutSlotList($dbh)
{
        $sql_req = "SELECT *
                    FROM Users 
                    WHERE `ID_Users` NOT IN (SELECT `ID_Users` FROM User_Timeslot) 
                         AND user_type = \"validated\"";
        
        $sql_req .= " ORDER BY Family_name";    
        $stmt = $dbh->prepare($sql_req);                                               
        $stmt->execute();
        
        return $stmt->fetchAll();
}

function getVolunteerListFromActivityName($dbh,$name)
{
    $sql_req = "SELECT Users.Name, Users.Family_name, Users.GSM  
                FROM Users, Timeslot, User_Timeslot
                WHERE Users.ID_Users = User_Timeslot.ID_Users
                AND Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot
                AND Timeslot.Description = :name
                AND user_type = 'validated'
                GROUP BY Users.ID_Users";
                
    $stmt = $dbh->prepare($sql_req);
    $stmt->bindParam(':name', $name);                       
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getBonusName($dbh)
{
    $sql_req = "SELECT Description from Bonus_items";
                
    $stmt = $dbh->prepare($sql_req);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getUserName($dbh, $userID)
{
    $sql_req = "SELECT Name, Family_name FROM Users WHERE ID_Users = :iduser";
    $stmt = $dbh->prepare($sql_req);
    $stmt->bindParam(':iduser', $userID);                                             
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        return $var["Family_name"]." ".$var["Name"];
    }
    
    return "UNKNOWN USER";
}

//////////////// UTILS FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function convertBonusList($list)
{
    $to_ret = array();
    foreach($list as $key=>$value)
    {
        $to_ret [] = $value["sum"]." fois : ".html_entity_decode($value["Description"],ENT_COMPAT | ENT_HTML401,"cp1252");
    }
    return $to_ret;
}

function buildVolunteerDocument($dbh,$userIDList, $bonus = true, $schedule = true)
{
    $page_array = array();
    foreach($userIDList as $k=>$v)
    {
        $user_schedule = getUserSchedule($dbh,$v["ID_Users"]);
        $bonus_list = convertBonusList(compute_bonus($dbh,$user_schedule));
        $user_name = getUserName($dbh, $v["ID_Users"]);
        
        $page = array();
        
        if($schedule)
        {
            $a = array();
            $a [] = "";

            if(count($user_schedule) == 0)
            {
                $a [] = "Pas de planning.";
            }
            
            foreach($user_schedule as $k=>$v)
            {
                $a [] = getSlotStartDateFromDBSlot($v)." : ".html_entity_decode(getSlotDescriptionFromDBSlot($v),ENT_COMPAT | ENT_HTML401,"cp1252");
            }

            $a [] = "";
            $a [] = "";
            $page["PLANNING"] = $a;
        }
        
        if($bonus)
        {
            $a = array();
            $a [] = "";
            
            if(count($user_schedule) == 0)
            {
                $a [] = "Pas de bonnus.";
            }
            else
            {
                $a = array_merge($a,$bonus_list);
            }

            $page["BONUS"] = $a;
        }
        $page_array [html_entity_decode($user_name,ENT_COMPAT | ENT_HTML401,"cp1252")] = $page;
    }
    buildSeveralPagePDF_v2($page_array);
    //buildSeveralPagePDF($page_array);
}

function sortScheduleFromActivityID($dbh, $id_array=array())
{
    $sql_req = "SELECT Timeslot.Description, Timeslot.Start_time, Timeslot.End_time, Timeslot.NumberOfPeople, Users.Name, Users.Family_name
                FROM (Timeslot LEFT JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot) LEFT JOIN Users ON Users.ID_Users = User_Timeslot.ID_Users";
    
    if(count($id_array) >0)
    {
        $sql_range = buildSQLParam(count($id_array));
        $sql_req .= " WHERE Timeslot.ID_Timeslot in (".$sql_range.") ";
    }
    
    $sql_req .= " ORDER BY Timeslot.Description ASC, Timeslot.Start_time ASC";

    $stmt = $dbh->prepare($sql_req);
    
    for($i = 0;$i<count($id_array);$i++)
    {
        $stmt->bindParam(":param".($i+1), $id_array[$i]);
    }
    
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    //var_dump($activities);
    
    $current_act = NULL;
    $to_return    = array();//tableau d'activite
    
    $current_day  = "2000-01-01 03:00:00";
    $activity_days = array();//tableau de jour
    
    $current_timeslot = "03:00";
    $timeslot_list = array();
    
    $user_list = array();//tableau d'utilisateur
    
    foreach($activities as $k=>$value)
    {
        //var_dump($to_return);echo "<BR /><BR />";
        //echo $value["Description"]." ".$value["Start_time"]." ".$value["Name"]."<BR />";
        $dtime = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
        $detime = fromMySQLDatetimeToPHPDatetime($value["End_time"]);
        //changement d'activite
        if($current_act != $value["Description"])
        {
            //echo "NEW DESC <BR />";
            //reset the local var
            $current_day = "2000-01-01 03:00:00";
            $current_timeslot = NULL;
            
            //add the new table
            unset($activity_days);
            $activity_days = array();
            $to_return[$value["Description"]] = &$activity_days;

            //set the new limit
            $current_act = $value["Description"];
        }
        
        //changement de jour
        if($value["Start_time"] > $current_day)
        {
            //echo "NEW DAY <BR />";
            //reset the local var
            $current_timeslot = NULL;
            
            //add the new table
            unset($timeslot_list);
            $timeslot_list = array();
            $activity_days [$dtime->format("Y-m-d")] = &$timeslot_list;
            
            //application de la nouvelle limite
            $current_day = buildLimitFromDBDatetime($value["Start_time"]);
        }
        
        //changement de slot horaire
        $current_time = $dtime->format("H:i")." - ".$detime->format("H:i");
        if($current_timeslot != $current_time)
        {
            //echo "NEW TIME <BR />";
            //add the new table
            unset($user_list);
            $user_list = array();
            $timeslot_list[$current_time] = &$user_list;
            
            for($i=0;$i<$value["NumberOfPeople"];$i++)
            {
                $user_list[$i] = "";
            }
            
            //application de la nouvelle limite
            $user_index = 0;
            $current_timeslot = $current_time;
        }
        
        if($value["Name"] != NULL and $value["Family_name"] != NULL)
        {
            $user_list [$user_index] = $value["Name"]." ".$value["Family_name"];
            $user_index += 1;
        }
        
    }
    
    return $to_return;
}

function getActivityIdFromDescr($dbh, $descr)
{
    $sql_req = "SELECT ID_Timeslot FROM Timeslot WHERE Description = :descr";
    $stmt = $dbh->prepare($sql_req);  
    $stmt->bindParam(':descr', $descr);                                       
    $stmt->execute();
    
    $res = $stmt->fetchAll();
    
    $to_ret = array();
    foreach($res as $k=>$v)
    {
        $to_ret [] = $v["ID_Timeslot"];
    }
    
    return $to_ret;
}

//////////////// MAIN ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if(array_key_exists("Deconnection",$_POST))
{
    unset($_SESSION['admin_id']);
}

try
{
    $try_to_auth = false;
    if(array_key_exists("username",$_POST) && array_key_exists("password",$_POST) && array_key_exists("connect",$_POST))
    {
        tryToAuth($dbh,'admin','admin_id');
        $try_to_auth = true;
    }

    if(!array_key_exists("admin_id",$_SESSION))
    {
        printAuthForm("listing.php");
    
        if($try_to_auth)
        {
            echo '<H3>Echec de connexion, le nom d\'utilisateur ou le mot de passe sont peu &ecirc;tre incorrects.  
            Le compte n\'a peu &ecirc;tre pas encore &eacute;t&eacute; valid&eacute; par un administrateur.  Si le probl&egrave;me persiste, contactez un administrateur: webmaster@folkfestivalmarsinne.be</H3>';
        }
    }
    else
    {
        if(array_key_exists("doc",$_GET))
        { 
            if($_GET["doc"] == "allschedule")
            {
                $user_list = getValidatedUserList($dbh); 
                buildVolunteerDocument($dbh,$user_list, false, true);
            }
            else if($_GET["doc"] == "allbonus")
            {
                $user_list = getValidatedUserList($dbh);
                buildVolunteerDocument($dbh,$user_list, true, false);
            }
            else if($_GET["doc"] == "allschedulebonus")
            {
                $user_list = getValidatedUserList($dbh); 
                buildVolunteerDocument($dbh,$user_list, true, true);
            }
            else if($_GET["doc"] == "allbonus_compact")
            {
                $bonus_name_list = getBonusName($dbh);
                
                //build a row (add name column)
                $header_name = array();
                $header_name [] = "Nom";
                $totaux = array();
                
                foreach($bonus_name_list as $k=>$v)
                {
                    $header_name [] = $v["Description"];
                    $totaux[$v["Description"]] = 0;
                }
                
                $user_id_list = getVolunteerList($dbh,false);
                $data = array();
                foreach($user_id_list as $k=>$v)
                {
                    $user_data = array();
                    $user_schedule = getUserSchedule($dbh,$v["ID_Users"]);
                    $bonus_list = compute_bonus($dbh,$user_schedule);
                    
                    foreach($header_name as $ki=>$vi)
                    {
                        if($vi == "Nom")
                        {
                            $user_data [] = html_entity_decode($v["Name"]." ".$v["Family_name"],ENT_COMPAT | ENT_HTML401,"cp1252");
                        }
                        else
                        {
                            $found = false;
                            foreach($bonus_list as $key=>$bonus)
                            {
                                if($bonus["Description"] == $vi)
                                {
                                    $user_data [] = $bonus["sum"];
                                    $totaux[$vi] += $bonus["sum"];
                                    $found = true;
                                    break;
                                }
                            }
                            
                            if(! $found)
                            {
                                $user_data [] = "0";
                            }
                        }
                    }
                    $data [] = $user_data;
                }                
                //les totaux
                $totaux_final = array();
                $totaux_final [] = "TOTAL";
                
                $header_finals = array();
                foreach($header_name as $ki=>$vi)
                {
                    $header_finals [] = html_entity_decode($vi,ENT_COMPAT | ENT_HTML401,"cp1252");
                    if($vi == "Nom"){continue;}
                    $totaux_final [] = $totaux[$vi];
                }
                
                $data [] = $totaux_final;
                
                $pdf = new FPDF();
                $pdf->AddPage("L");
                FancyTable($pdf, $header_finals,$data);
                $pdf->Output();
                exit();
            }
            else if($_GET["doc"] == "allschedule_grid")
            {
                $schedule_grid = sortScheduleFromActivityID($dbh);
                build_schedule_activities($schedule_grid);
            }
            else if($_GET["doc"] == "backupvolunteer")
            {
                $list = getVolunteerList($dbh,true);
                printVolunteerList($list, "BENEVOLES DE SECOURS");
                
            }
            else if($_GET["doc"] == "volunteer")
            {
                $list = getVolunteerList($dbh,false);
                printVolunteerList($list,"BENEVOLES");
            }
            else if($_GET["doc"] == "volunteer_without_slot")
            {
                $list = getVolunteerWithoutSlotList($dbh);
                printVolunteerList($list,"BENEVOLES SANS SLOT HORAIRE",false);
            }
            else
            {
                if($DEBUG)
                {
                    echo "unknown command : ".$_GET["doc"];
                }
            }
        }
        else if(array_key_exists("getbonus",$_POST))
        {
            if(!array_key_exists("volunteer_id",$_POST))
            {
                echo "Aucun id d'utilisateur n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $user_list = array(array("ID_Users"=>$_POST["volunteer_id"]));
            buildVolunteerDocument($dbh,$user_list, true, false);
        }
        else if(array_key_exists("getschedule",$_POST))
        {
            if(!array_key_exists("volunteer_id",$_POST))
            {
                echo "Aucun id d'utilisateur n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $user_list = array(array("ID_Users"=>$_POST["volunteer_id"]));
            buildVolunteerDocument($dbh,$user_list, false, true);
        }
        else if(array_key_exists("getschedulebonus",$_POST))
        {
            if(!array_key_exists("volunteer_id",$_POST))
            {
                echo "Aucun id d'utilisateur n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $user_list = array(array("ID_Users"=>$_POST["volunteer_id"]));
            buildVolunteerDocument($dbh,$user_list, true, true);
        }
        else if(array_key_exists("getactschedule",$_POST))
        {
            if(!array_key_exists("activity_name",$_POST))
            {
                echo "Aucun nom d'activité n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $id_activity_list = getActivityIdFromDescr($dbh, $_POST["activity_name"]);
            $schedule_grid = sortScheduleFromActivityID($dbh, $id_activity_list);
            build_schedule_activities($schedule_grid);
        }
        else if(array_key_exists("getvolunteer",$_POST))
        {
            if(!array_key_exists("activity_name",$_POST))
            {
                echo "Aucun nom d'activité n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $list = getVolunteerListFromActivityName($dbh,$_POST["activity_name"]);
            printVolunteerList($list,"BENEVOLE POUR ".html_entity_decode($_POST["activity_name"],ENT_COMPAT | ENT_HTML401,"cp1252"));
        }
        else
        {
            $user_list = getValidatedUserList($dbh);
            $total_slot_count = getAllSlotCount($dbh);
            $recorded_slot_count = getRecordedSlotCount($dbh);
            $activity_list = getActivityList($dbh);
            
            echo "<html><head></head><body>";
            
            echo "<form METHOD=\"POST\" ACTION=\"agenda.php\"><INPUT type=\"submit\" name=\"Deconnection\" value=\"Deconnection\"></form>";
            
            echo "Nombre d'utilisateur inscrit : ".getAllUserExceptAdmin($dbh)."<BR />";
            echo "Nombre d'utilisateur valid&eacute;s : ".count($user_list)."<BR />"; 
            echo "Nombre d'utilisateur valid&eacute;s sans aucune plage : ".getValidatedUserWithoutSlotCount($dbh)."<BR />"; 
            
            echo "Nombre d'utilisateur non valid&eacute;s : ".getNonValidatedUserCount($dbh)."<BR />"; 
            echo "Nombre de plages horaires TOTALE : ".$total_slot_count."<BR />";
            echo "Nombre de plages horaires RESERVEES : ".$recorded_slot_count."<BR />";
            echo "Nombre de plages horaires LIBRE : ".($total_slot_count - $recorded_slot_count)."<BR />";
            
            echo "<BR />";
        
            //-planning de tous les utilisateurs (1 par page)
            echo"<a href=\"listing.php?doc=allschedule\" target=\"_blank\">Obtenir la liste des horaires de chaque utilisateur (1 utilisateur par page)</a><BR />";
            //-bonus de tous les utilisateurs (1 par page)
            echo"<a href=\"listing.php?doc=allbonus\" target=\"_blank\">Obtenir la liste des bonus de chaque utilisateur (1 utilisateur par page)</a><BR />";
            //-bonus/planning de tous les utilisateurs (1 par page)
            echo"<a href=\"listing.php?doc=allschedulebonus\" target=\"_blank\">Obtenir la liste des horaires/bonus de chaque utilisateur (1 utilisateur par page)</a><BR />";
			//-bonus de tous les utilisateurs (le plus compact possible)
			echo"<a href=\"listing.php?doc=allbonus_compact\" target=\"_blank\">Obtenir la liste des bonus de chaque utilisateur (list compacte)</a><BR />";	
			//-horaire globale (grille avec le nom des gens)
			echo"<a href=\"listing.php?doc=allschedule_grid\" target=\"_blank\">Obtenir la liste des horaires de chaque utilisateur (grille compacte)</a><BR />";
			//-liste benevole de secour (nom + num de gsm)
			echo"<a href=\"listing.php?doc=backupvolunteer\" target=\"_blank\">Obtenir la liste de tous les utilisateur de secours</a><BR />";
			//-liste benevole normaux (nom + num de gsm)
			echo"<a href=\"listing.php?doc=volunteer\" target=\"_blank\">Obtenir la liste de tous les utilisateur normaux</a><BR />";
			//-liste benevoles validées sans aucune plage (nom + num de gsm)
			echo"<a href=\"listing.php?doc=volunteer_without_slot\" target=\"_blank\">Obtenir la liste de tous les utilisateur valid&eacute;s sans aucune plage</a><BR /><BR />";
			
            //-planning d'un utilisateur / -bonus d'un utilisateur / -bonus/planning d'un utilisateur
            echo "<form METHOD=\"POST\" ACTION=\"listing.php\" TARGET=\"_blank\"><select name=\"volunteer_id\">";
            
            foreach($user_list as $k=>$v)
            {
                echo "<OPTION value=\"".$v["ID_Users"]."\">".$v["Name"]." ".$v["Family_name"]."(".$v["user_type"]." ".$v["Reliability"].")"."</OPTION>";
            }
            echo "</select><INPUT type=\"submit\" name=\"getbonus\" value=\"Obtenir la liste des bonus\"><INPUT type=\"submit\" name=\"getschedule\" value=\"Obtenir l'horaire\"><INPUT type=\"submit\" name=\"getschedulebonus\" value=\"Obtenir l'horaire/bonus\"></form>";
        
            //-horaire par activit&eacute; (grille avec le nom des gens) / -liste benevole par activit&eacute; (nom + num de gsm)
            echo "<form METHOD=\"POST\" ACTION=\"listing.php\" TARGET=\"_blank\"><select name=\"activity_name\">";
            
            foreach($activity_list as $k=>$v)
            {
                echo "<OPTION value=\"".htmlspecialchars($v["Description"])."\">".$v["Description"]."</OPTION>";
            }
            echo "</select><INPUT type=\"submit\" name=\"getactschedule\" value=\"Obtenir le planning de l'activit&eacute;\">
            <INPUT type=\"submit\" name=\"getvolunteer\" value=\"Obtenir la liste des b&eacute;n&eacute;voles de cette activit&eacute;\"></form>";
                    
            echo "</body></html>";
        }
    }
}
catch(PDOException $err)
{
    if($DEBUG)
    {
        echo "Erreur: ".$err;
    }

    echo "<BR/>Il semblerait qu'un probleme avec la base de donn&eacute;es ait eu lieu.  Si le probl&egrave;me persiste, contactez l'administrateur du site: webmaster@folkfestivalmarsinne.be";
}   
$dbh = null;
?>
