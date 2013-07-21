<?php session_start(); 
include 'config.inc.php';
include 'function.inc.php';
require('fpdf.php');

//TODO
    //-mettre le nom de l'utilisateur au bon endroit dans le document
    //-quand il y a zero plage et zero bonus, l'indiquer

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

function tryToAuth($dbh)
{   
    unset($_SESSION['admin_id']);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname and user_type = 'admin' and password = :password");        
    $stmt->bindParam(':uname', $_POST['username']);
    $md5_mdp = md5($_POST['password']);
    $stmt->bindParam(':password', $md5_mdp);
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        $_SESSION['admin_id'] = $var["ID_Users"];
    }
}
    
function printAuthForm()
{
    ?>
    <form method="POST" action="./listing.php">
            <table BORDER=0>
                <tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username"></td></tr>
                <tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password"></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td>                              <td><br /><INPUT type="submit" name="connect" value="Se connecter"></td></tr>
            </table>
        </form>
    <?php
}

function getUserList($dbh)
{
    $sql_req = "SELECT ID_Users, Name, Family_name, user_type, Reliability FROM Users WHERE user_type != 'admin'";
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->execute();
    return $stmt->fetchAll();
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
                AND user_type = 'validated'";
                
    $stmt = $dbh->prepare($sql_req);
    $stmt->bindParam(':name', $name);                       
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function printVolunteerList($list)
{
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    
    foreach($list as $k=>$v) //Name, Family_name,GSM  
    {
        $pdf->MultiCell(0,10,$v["Name"]." ".$v["Family_name"]." : ".$v["GSM"]);
    }
     
    $pdf->Output();
    exit();
}

function buildPDF($list)
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

function buildSeveralPagePDF($list)
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

function convertBonusList($list)
{
    $to_ret = array();
    foreach($list as $key=>$value)
    {
        $to_ret [] = $value["sum"]." fois : ".$value["Description"];
    }
    return $to_ret;
}

function getBonusName($dbh)
{
    $sql_req = "SELECT Description from Bonus_items";
                
    $stmt = $dbh->prepare($sql_req);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function FancyTable($pdf,$header, $data)
{
    // Couleurs, épaisseur du trait et police grasse
    $pdf->SetFillColor(255,0,0);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(128,0,0);
    $pdf->SetLineWidth(.3);
    $pdf->SetFont('Arial','B',7);
    // En-tête
    $w = array(30, 22, 38, 46, 55, 16, 18, 32);
    for($i=0;$i<count($w);$i++)
        $pdf->Cell($w[$i],7,$header[$i],1,0,'C',true);
    $pdf->Ln();
    // Restauration des couleurs et de la police
    $pdf->SetFillColor(224,235,255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('','',10);
    // Données
    $fill = false;
    $counter = 0;
    foreach($data as $row)
    {
        $pdf->Cell($w[0],6,$row[0],'LR',0,'L',$fill);
        $pdf->Cell($w[1],6,$row[1],'LR',0,'L',$fill);
        $pdf->Cell($w[2],6,$row[2],'LR',0,'L',$fill);
        $pdf->Cell($w[3],6,$row[3],'LR',0,'L',$fill);
        $pdf->Cell($w[4],6,$row[4],'LR',0,'L',$fill);
        $pdf->Cell($w[5],6,$row[5],'LR',0,'L',$fill);
        $pdf->Cell($w[6],6,$row[6],'LR',0,'L',$fill);
        $pdf->Cell($w[7],6,$row[7],'LR',0,'L',$fill);
        
        $pdf->Ln();
        $fill = !$fill;
        
        $counter += 1;
        if($counter == 20)
        {
            $pdf->AddPage("L");
            $counter = 0;
        }
    }
    // Trait de terminaison
    $pdf->Cell(array_sum($w),0,'','T');
}

if(array_key_exists("Deconnection",$_POST))
{
    unset($_SESSION['admin_id']);
}

try
{
    $try_to_auth = false;
    if(array_key_exists("username",$_POST) && array_key_exists("password",$_POST) && array_key_exists("connect",$_POST))
    {
        tryToAuth($dbh);
        $try_to_auth = true;
    }

    if(!array_key_exists("admin_id",$_SESSION))
    {
        printAuthForm();
    
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
                $page_array = array();
                $user_list = getUserList($dbh);
                
                foreach($user_list as $k=>$v)
                {
                    $user_schedule = getUserSchedule($dbh,$v["ID_Users"]);

                    $a = array();
                    $a [] = "PLANNING";
                    $a [] = "";

                    foreach($user_schedule as $k=>$v)
                    {
                        $a [] = getSlotStartDateFromDBSlot($v)." : ".getSlotDescriptionFromDBSlot($v);
                    }
                    
                    $page_array [] = $a;
                }
                buildSeveralPagePDF($page_array);
            }
            else if($_GET["doc"] == "allbonus")
            {
                $page_array = array();
                $user_list = getUserList($dbh);
                
                foreach($user_list as $k=>$v)
                {
                    $user_schedule = getUserSchedule($dbh,$v["ID_Users"]);
                    $bonus_list = convertBonusList(compute_bonus($dbh,$user_schedule));

                    $a = array();
                    $a [] = "BONUS";
                    $a [] = "";
                    $a = array_merge($a,$bonus_list);
                    
                    $page_array [] = $a;
                }
                buildSeveralPagePDF($page_array);
            }
            else if($_GET["doc"] == "allschedulebonus")
            {
                $page_array = array();
                $user_list = getUserList($dbh);
                
                foreach($user_list as $k=>$v)
                {
                    $user_schedule = getUserSchedule($dbh,$v["ID_Users"]);
                    $bonus_list = convertBonusList(compute_bonus($dbh,$user_schedule));

                    $a = array();
                    $a [] = "PLANNING";
                    $a [] = "";

                    foreach($user_schedule as $k=>$v)
                    {
                        $a [] = getSlotStartDateFromDBSlot($v)." : ".getSlotDescriptionFromDBSlot($v);
                    }

                    $a [] = "";
                    $a [] = "";
                    $a [] = "BONUS";
                    $a [] = "";
                    $a = array_merge($a,$bonus_list);
                    $page_array [] = $a;
                }
                buildSeveralPagePDF($page_array);
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
                            $user_data [] = $v["Name"]." ".$v["Family_name"];
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
                
                foreach($header_name as $ki=>$vi)
                {
                    if($vi == "Nom"){continue;}
                    $totaux_final [] = $totaux[$vi];
                }
                
                $data [] = $totaux_final;
                
                $pdf = new FPDF();
                $pdf->AddPage("L");
                FancyTable($pdf, $header_name,$data);
                $pdf->Output();
                exit();
            }
            else if($_GET["doc"] == "allschedule_grid")
            {
                //TODO
            }
            else if($_GET["doc"] == "backupvolunteer")
            {
                $list = getVolunteerList($dbh,false);
                printVolunteerList($list);
                
            }
            else if($_GET["doc"] == "volunteer")
            {
                $list = getVolunteerList($dbh,true);
                printVolunteerList($list);
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
            
            $user_schedule = getUserSchedule($dbh,$_POST["volunteer_id"]);
            $bonus_list = convertBonusList(compute_bonus($dbh,$user_schedule));
            
            $a = array();
            $a [] = "BONUS";
            $a [] = "";
            $a = array_merge($a,$bonus_list);

            buildPDF($a);
        }
        else if(array_key_exists("getschedule",$_POST))
        {
            if(!array_key_exists("volunteer_id",$_POST))
            {
                echo "Aucun id d'utilisateur n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $user_schedule = getUserSchedule($dbh,$_POST["volunteer_id"]);
            
            $a = array();
            $a [] = "PLANNING";
            $a [] = "";
            
            foreach($user_schedule as $k=>$v)
            {
                $a [] = getSlotStartDateFromDBSlot($v)." : ".getSlotDescriptionFromDBSlot($v);
            }
            
            buildPDF($a);
        }
        else if(array_key_exists("getschedulebonus",$_POST))
        {
            if(!array_key_exists("volunteer_id",$_POST))
            {
                echo "Aucun id d'utilisateur n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $user_schedule = getUserSchedule($dbh,$_POST["volunteer_id"]);
            $bonus_list = convertBonusList(compute_bonus($dbh,$user_schedule));
            
            $a = array();
            $a [] = "PLANNING";
            $a [] = "";
            
            foreach($user_schedule as $k=>$v)
            {
                $a [] = getSlotStartDateFromDBSlot($v)." : ".getSlotDescriptionFromDBSlot($v);
            }
            
            $a [] = "";
            $a [] = "";
            $a [] = "BONUS";
            $a [] = "";
            $a = array_merge($a,$bonus_list);
            
            buildPDF($a);
        }
        else if(array_key_exists("getactschedule",$_POST))
        {
            if(!array_key_exists("activity_name",$_POST))
            {
                echo "Aucun nom d'activité n'a été fourni, impossible de générer le document";
                exit();
            }
            
            //TODO
        }
        else if(array_key_exists("getvolunteer",$_POST))
        {
            if(!array_key_exists("activity_name",$_POST))
            {
                echo "Aucun nom d'activité n'a été fourni, impossible de générer le document";
                exit();
            }
            
            $list = getVolunteerListFromActivityName($dbh,$_POST["activity_name"]);
            printVolunteerList($list);
        }
        else
        {
            $user_list = getUserList($dbh);
            $total_slot_count = getAllSlotCount($dbh);
            $recorded_slot_count = getRecordedSlotCount($dbh);
            $activity_list = getActivityList($dbh);
            
            echo "<html><head></head><body>";
            
            echo "<form METHOD=\"POST\" ACTION=\"agenda.php\"><INPUT type=\"submit\" name=\"Deconnection\" value=\"Deconnection\"></form>";
            
            echo "Nombre d'utilisateur inscrit : ".count($user_list)."<BR />";
            echo "Nombre d'utilisateur valid&eacute;s : ".getValidatedUserCount($dbh)."<BR />"; 
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
			echo"<a href=\"listing.php?doc=volunteer\" target=\"_blank\">Obtenir la liste de tous les utilisateur normaux</a><BR /><BR />";
			
            //-planning d'un utilisateur / -bonus d'un utilisateur / -bonus/planning d'un utilisateur
            echo "<form METHOD=\"POST\" ACTION=\"listing.php\"><select name=\"volunteer_id\">";
            
            foreach($user_list as $k=>$v)
            {
                echo "<OPTION value=\"".$v["ID_Users"]."\">".$v["Name"]." ".$v["Family_name"]."(".$v["user_type"]." ".$v["Reliability"].")"."</OPTION>";
            }
            echo "</select><INPUT type=\"submit\" name=\"getbonus\" value=\"Obtenir la liste des bonus\"><INPUT type=\"submit\" name=\"getschedule\" value=\"Obtenir l'horaire\"><INPUT type=\"submit\" name=\"getschedulebonus\" value=\"Obtenir l'horaire/bonus\"></form>";
        
            //-horaire par activit&eacute; (grille avec le nom des gens) / -liste benevole par activit&eacute; (nom + num de gsm)
            echo "<form METHOD=\"POST\" ACTION=\"listing.php\"><select name=\"activity_name\">";
            
            foreach($activity_list as $k=>$v)
            {
                echo "<OPTION value=\"".$v["Description"]."\">".$v["Description"]."</OPTION>";
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
?>
