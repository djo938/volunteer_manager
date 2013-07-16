<?php

//TODO 
    //-gerer les erreurs DB (avec des exceptions)
    //-faire passer les mdp en MD5
    //-bouton disconnect
    //-les bonus tienne bien compte des conflits de plage?
    //-lorsqu'on depasse le nombre de slot possible, ne pas afficher les conflits
    //-faire la transaction

session_start();
include 'config.inc.php';
$dbh        = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);
$error_list = array(); //liste des erreurs rencontrees

function fromMySQLDatetimeToPHPDatetime($mysql_datetime)
{
    return DateTime::createFromFormat("Y-m-d H:i:s",$mysql_datetime);
}

function compute_bonus($slot_list)
{
    global $dbh;
    //echo 'BONUS <BR />';
    
    //var_dump($slot_list);
    
    $value_to_print = array();
    if(count($slot_list) > 0)
    {
        $bonus_to_load = array();
    
        $first = true;
        $req_part = "";
        //$i = 1;
        foreach($slot_list as $index=>$value)
        {
            
            if(array_key_exists($value["Bonus_cat"],$bonus_to_load))
            {
                $bonus_to_load[intval($value["Bonus_cat"])] += 1;
            }
            else
            {
                $bonus_to_load[intval($value["Bonus_cat"])] = 1;
            
                if($first)
                {
                    //$req_part = "(cat = :cat".$i." AND threshold <= :threshold".$i.")";
                    $req_part = "(cat = ".$value["Bonus_cat"]." AND threshold <= :threshold".$value["Bonus_cat"].")";
                    $first = false;
                }
                else
                {
                    //$req_part .= " OR (cat = :cat".$i." AND threshold <= :threshold".$i.")";
                    $req_part .= " OR (cat = ".$value["Bonus_cat"]." AND threshold <= :threshold".$value["Bonus_cat"].")";
                }
                //$i += 1;
            }
        }
    
        $SQL = "SELECT description, max(threshold) FROM Bonus WHERE ".$req_part." GROUP BY cat";
        //$SQL = "SELECT description, max(threshold) FROM Bonus WHERE (cat = 1 AND threshold <= 1) OR (cat = 2 AND threshold <= 2) GROUP BY cat";
        //echo $SQL."<BR />";
        $stmt = $dbh->prepare($SQL);
    
        //$i = 1;
        //var_dump($bonus_to_load);
        foreach($bonus_to_load as $key=>$value)
        {
            //echo gettype($key)." ".gettype($value)."<BR />";
            //$stmt->bindParam(":cat".$i,$key,PDO::PARAM_INT);
            $stmt->bindParam(":threshold".$key,$value,PDO::PARAM_INT);
            //print "BIND ".$key." VALUE ".$value." <BR />";
            //$i += 1;
        }
    
        $stmt->execute();
        //echo '<BR /><BR />';
        //$stmt->debugDumpParams();
        //echo '<BR /><BR />';
        $a = $stmt->fetchAll();
    
        //var_dump($a);
    
        foreach($a as $index=>$value)
        {
            $value_to_print[] = $value["description"];
        }
    }
    return $value_to_print;
}

function sortData($db_data, $timeslot_id_array)
{
    //classement par jour, puis par activite
    //data pre parsing
    $selected_db_items = array();
    $sorted_data = array();
    $next_limit  = "2000-01-01 03:00:00";
    $current_day = array();

    //var_dump($timeslot_id_array);

    foreach( $db_data as $key=>$value)
    {
        //on est toujours le jours courant ?
    	if($value["Start_time"] > $next_limit)
    	{
    	    //echo count($current_day)."<BR />";
    	    //var_dump($current_day);echo "<BR /><BR />";
            //nouvelle ensemble des events du jour
    		unset($current_day);
    		$current_day = array();

            //creation de la nouvelle limite
            $new_next_limit = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);

            if($new_next_limit->format("H:i") > "03:00")
            {
                $new_next_limit->add(new DateInterval("P1D")); //si on est avant minuit, on ajoute un jour de + a la limite
            }
            
            $new_next_limit->setTime(3,0,0); //on definit la limite de la journee a 3h00 du matin

    		$start_day = clone $new_next_limit;
            $start_day->sub(new DateInterval("P1D"));

            //ajout de l'ensemble du jour dans la liste des jours
    		$sorted_data[$start_day->format("l d F")] = &$current_day;

    		//application de la nouvelle limite
    		$next_limit = $new_next_limit->format("Y-m-d H:i:s");
    	}

        //l'event existe deja aujourd'hui ?
    	if( !array_key_exists($value["Description"],$current_day))
    	{
    		$current_day[$value["Description"]] = array();
    	}

        //ajout de l'event
    	$current_day[$value["Description"]][] = $value;
    	
    	//l'objet fait-il partie de ceux selectionne? (pour le calcul des bonus)
    	if(in_array($value["ID_Timeslot"],$timeslot_id_array))
    	{
    	    $selected_db_items[] = $value;
    	}
    }
    //echo count($current_day)."<BR />";
    
    return array($sorted_data,$selected_db_items);
}

function printForm($sorted_data, $timeslot_id_array)
{
    echo "<form METHOD=\"POST\" ACTION=\"agenda.php\">";

    foreach( $sorted_data as $key=>$current_day)
    {
        if(count($current_day) == 0)
        {
            continue;
        }

    	echo "<h1>".$key."</h1><table>";

    	foreach( $current_day as $desc=>$occurrences)
    	{
    	    if(count($occurrences) == 0)
            {
                continue;
            }

    		echo "<tr><td>".$desc."</td><td><table>\n";

    		$first_line  = "<tr>";
    		$second_line = "<tr>";
    		foreach( $occurrences as $index=>$occurrence)
    		{
    		    //cocher les cases en provenances des erreurs ou du test
    		    if(in_array($occurrence["ID_Timeslot"], $timeslot_id_array))
    		    {
    		        $checked = "checked";
    		    }
    		    else
    		    {
    		        $checked = "";
    		    }

    			$first_line  .= "<td><INPUT type=\"checkbox\" name=\"timeslot_id[]\" value=\"".$occurrence["ID_Timeslot"]."\" ".$checked." />".fromMySQLDatetimeToPHPDatetime($occurrence["Start_time"])->format("H:i")."-".fromMySQLDatetimeToPHPDatetime($occurrence["End_time"])->format("H:i")."</td>\n";
    		    $second_line .= "<td>".$occurrence["NumberOfPeople"]." place(s)</td>\n";
    		}
    		$first_line  .= "</tr>";
    		$second_line .= "</tr>";

    		echo $first_line.$second_line."</table></td></tr>";
    	}
    	echo "</table>";
    }
    ?>
    <BR/><INPUT type="submit" name="Test" value="Test">
    <INPUT type="submit" name="Envoyer" value="Envoyer">

    </form>
    <?php
}

function recordData($timeslot_id_array)
{
    global $dbh,$error_list, $AGENDA_MIN_SLOT, $AGENDA_MAX_SLOT;      
    
    //verifier le nombre de slot
    if($AGENDA_MIN_SLOT > count($timeslot_id_array))
    {
        $error_list[] = "Le nombre de slot minimum possible est de ".$AGENDA_MIN_SLOT;
    }
    
    if($AGENDA_MAX_SLOT < count($timeslot_id_array))
    {
        $error_list[] = "Le nombre de slot maximum possible est de ".$AGENDA_MAX_SLOT;
    }
    
    //verifier que les slots ne s'overlape pas
    //on recupere les valeurs des slots selectionnes
    $first = true;
    $sql_param = "";
    for($i = 0;$i<count($timeslot_id_array);$i++)
    {
        if($first)
        {
            $sql_param = ":param".($i+1);
            $first = false;
            continue;
        }
        $sql_param .= ",:param".($i+1);
    }

    $stmt = $dbh->prepare("SELECT * from Timeslot where ID_Timeslot in (".$sql_param.")");
    
    for($i = 0;$i<count($timeslot_id_array);$i++)
    {
        $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
    }
    
    $stmt->execute();
    $selected_value = $stmt->fetchAll();
    
    //on compare les slots, ils ne doivent pas etre en overlap
    for($i=0;$i<count($selected_value);$i++)
    {
        for($j=$i+1;$j<count($selected_value);$j++)
        {
            if(!(($selected_value[$j]["Start_time"] < $selected_value[$i]["Start_time"] 
            && $selected_value[$j]["End_time"] <= $selected_value[$i]["Start_time"])
            ||($selected_value[$j]["Start_time"] >= $selected_value[$i]["End_time"] && $selected_value[$j]["End_time"] > $selected_value[$i]["End_time"])))
            {
                //mettre la date et l'heure des slots
                $plageJ_datetime_start = fromMySQLDatetimeToPHPDatetime($selected_value[$j]["Start_time"]);
                $plageJ_datetime_end = fromMySQLDatetimeToPHPDatetime($selected_value[$j]["End_time"]);
                
                $plageI_datetime_start = fromMySQLDatetimeToPHPDatetime($selected_value[$j]["Start_time"]);
                $plageI_datetime_end = fromMySQLDatetimeToPHPDatetime($selected_value[$j]["End_time"]);
                
                $error_list[] = "Conflit le ".$plageI_datetime_start->format("l d F").", la plage \"".$selected_value[$i]["Description"]."\" ".$plageI_datetime_start->format("H:i")."-".$plageI_datetime_end->format("H:i")." entre en conflit avec la plage \"".$selected_value[$j]["Description"]."\" ".$plageJ_datetime_start->format("H:i")."-".$plageJ_datetime_end->format("H:i");
            }
        }
    }
    
//////// ENREGISTREMENT DES DONNEES ////////////////////////////////////////////////////////////////////////
    if(count($error_list) == 0)
    {
        if(array_key_exists("Envoyer",$_POST))
        {
            //TODO on ajoute dans la DB
                //transaction
                    //1) on verrouille
                    
                    //2) on verifie la disponibilite des plages
                    
                    //3) on reserve les plages
                    
                    //4) on commit
            
            //TODO si pas d'erreur, ne pas afficher le planning
                //TODO en faisant passer la variable $submitted_calendar a true
                //return true;
        }
    }
    
    return false;
}

/*function isCalendarValidated()
{
    global $dbh; 
    
    //l'utilisateur a t'il deja valide son agenda ?
    $stmt = $dbh->prepare("SELECT * FROM User_Timeslot ut, Users u WHERE ut.ID_Users = u.ID_Users AND u.ID_Users = :user_id"); 
    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    $a = $stmt->fetchAll();

    if(count($a) == 0) //non
    {
        return false;
    }
    
    return true;
}
*/
function printError()
{
    global $error_list;
    
    //afficher les erreurs
    if(count($error_list) > 0)
    {
        echo "<h2>Erreur(s)</h2>";
        foreach($error_list as $index=>$error)
        {
            echo "<h3>".$error."</h3>";
        }
    }
}

function printBonus($selected_db_items)
{
    $bonus = compute_bonus($selected_db_items);
    if(count($bonus) > 0)
    {
        echo "<h2> Bonus </h2>";
        foreach($bonus as $key=>$value)
        {
            echo $value."<BR />";
        }
    }
}

function findMissingSlot($timeslot_id_array, $available_slot)
{
    global $dbh, $error_list;
    
    //calculer si des plages ont disparues depuis les erreurs ou le test
    $first = true;
    $sql_param = "";
    $timeslot_id_array_unavailable = array();
    $i = 1;
    
    foreach($timeslot_id_array as $key=>$value)
    {
        $found = false;
        foreach( $available_slot as $db_key=>$db_item)
        {
            if($value == $db_item["ID_Timeslot"])
            {
                $found = true;
                break;
            }
        }

        if(! $found)
        {
            //on construit la requete des slots qui ne sont plus disponible
            if($first)
            {
                $sql_param = ":param".($i+1);
                $first = false;
            }
            else
            {
                $sql_param .= ",:param".($i+1);
            }
            
            $timeslot_id_array_unavailable[] = $value;
        }
    }

    //on recupere les slots non disponible (pour obtenir leurs informations) et on les ajoute dans la liste des erreurs
    if(count($timeslot_id_array_unavailable) > 0)
    {   
        //on recupere tout les timeslot non disponible       
        $stmt = $dbh->prepare("SELECT * from Timeslot t, Users u where t.ID_Timeslot in (".$sql_param.") AND t.Reliability_needed <= u.Reliability AND u.ID_Users = :user_id");

        for($i = 0;$i<count($timeslot_id_array_unavailable);$i++)
        {
            $stmt->bindParam(":param".($i+1), $timeslot_id_array_unavailable[$i]);
        }
        
        $stmt->bindParam(":user_id",$_SESSION['user_id']);
        $stmt->execute();
        $unavailable_slot = $stmt->fetchAll();
        
        //on les affiche
        foreach( $unavailable_slot as $key=>$value)
        {
            $plage_datetime_start = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
            $plage_datetime_end = fromMySQLDatetimeToPHPDatetime($value["End_time"]);
            $error_list[] = "La plage \"".$value["Description"]."\" ".$plage_datetime_start->format("H:i")."-".$plage_datetime_end->format("H:i");
        }
    }
}

function getSlotDescriptionFromDBSlot($value)
{
    $datetime_start = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
    $datetime_end = fromMySQLDatetimeToPHPDatetime($value["End_time"]);
    
    return "\"".$value["Description"]."\" ".$datetime_start->format("H:i")."-".$datetime_end->format("H:i");
}

function getAllAvailableSlot()
{
    global $dbh;
    
    $stmt = $dbh->prepare("SELECT * FROM Timeslot t, Users u WHERE (SELECT count(*) FROM User_Timeslot ut 
                                                                           WHERE ut.ID_Timeslot = t.ID_Timeslot) < t.NumberOfPeople
                                                             AND t.Reliability_needed <= u.Reliability AND u.ID_Users = :user_id"); 

    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserSlot()
{
    global $dbh;
    $stmt = $dbh->prepare("SELECT t.* FROM Timeslot t, User_Timeslot ut, Users u 
                                      WHERE t.ID_Timeslot  = ut.ID_Timeslot 
                                            AND u.ID_Users = ut.ID_Users
                                            AND u.ID_Users = :user_id
                                            ORDER BY t.Start_time ASC"); 

    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    return $stmt->fetchAll();
}

function printUserSlot($user_slot)
{
    echo "<h2> Plages horaires </h2>";
    foreach($user_slot as $key=>$value)
    {
        echo getSlotDescriptionFromDBSlot($value)."<BR />";
    }
}

function tryToAuth()
{
    global $dbh;
    
    unset($_SESSION['user_id']);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname and user_type = 'validated'");        
    $stmt->bindParam(':uname', $_POST['username']);
	$stmt->execute();
    
	if( ($var = $stmt->fetch()) &&  $_POST['password'] == $var["password"])
	{
	    $_SESSION['user_id'] = $var["ID_Users"];
	}
}

function printAuthForm()
{
    ?>
    <form method="POST" action="./agenda.php">
    	    <table BORDER=0>
    	    	<tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username"></td></tr>
    	    	<tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password"></td></tr>
    	    	<tr><td></td><td></td></tr>
    	    	<tr><td></td>                              <td><br /><INPUT type="submit" value="Se connecter"></td></tr>
    	    </table>
    	</form>
    <?php
}

//////////////// AUTH ///////////////////////////////////////////////////
$try_to_auth = false;

//TODO uncomment me
/*if(isset($_POST['username']) && isset($_POST['password']))
{
    tryToAuth();
}*/

$_SESSION['user_id']          = '2'; //TODO erase me

if (!isset($_SESSION['user_id']))//est-on authentifie?
{
    //on affiche le formulaire d'auth
    printAuthForm();
    
    if($try_to_auth)
    {
        echo '<H3>Echec de connexion</H3>';
    }
}
else
{
//////////////// GET DATA ///////////////////////////////////////////////////
    //recuperer la liste des cases cochees
        //id des slots selectionnes par l'utilisateur
    if(array_key_exists("timeslot_id", $_POST))
    {
        $timeslot_id_array = $_POST["timeslot_id"];
        
        //on s'assure que l'on a bien recuperer un tableau
        if(! is_array($timeslot_id_array))
        {
            $timeslot_id_array = array();
        }
        
        //on s'assure que l'ensemble de valeur du tableau sont de type numerique
        foreach($timeslot_id_array as $k=>$v)
        {
            if(!is_numeric($v))
            {
                $timeslot_id_array = array();
                break;
            }
        }
    }
    else
    {
        $timeslot_id_array = array(); 
    }
    
    $submitted_calendar = false;
    $user_slot = getUserSlot();//on recupere les slots que l'utilisateur a reserve 
    if(count($user_slot) > 0) //est ce que l'utilisateur a deja reserve ?
    {
        $submitted_calendar = true;
    }

//////////////// RECORD/TEST ///////////////////////////////////////////////////

    //si l'utilisateur n'a pas encore fait de reservation et qu'il y a des donnees de post, on peut tenter un test ou un submit
    if(!$submitted_calendar && (array_key_exists("Test",$_POST) || array_key_exists("Envoyer",$_POST) )) // SI donnees de post
    {
        $submitted_calendar = recordData($timeslot_id_array); //on essaye d'enregistrer ou de tester les donnees
    }
    
//////////////// PRINT /////////////////////////////////////////////////////////

    if($submitted_calendar) //l'utilisateur a deja enregistre son agenda
    {        
        printUserSlot($user_slot); //on affiche son planning
        printBonus($user_slot);    //on affiche ses bonus     
    }
    else //l'utilisateur n'a pas encore enregistre son agenda
    {
        $available_slot = getAllAvailableSlot(); //on recupere les slots horaire encore disponible        
        list($sorted_data,$selected_db_items) = sortData($available_slot, $timeslot_id_array); //on trie les données par jour et par event
        findMissingSlot($timeslot_id_array, $available_slot); //identification des plages qui aurait disparue depuis le précédent test
        printBonus($selected_db_items); //affichage des bonus, s'il y en a
        printError(); //on affiche les erreurs, s'il y en a
        printForm($sorted_data, $timeslot_id_array); //affichage du formulaire permettant de choisir ses slots

    }
}
?>





