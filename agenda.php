<?php

function fromMySQLDatetimeToPHPDatetime($mysql_datetime)
{
    return DateTime::createFromFormat("Y-m-d H:i:s",$mysql_datetime);
}

function compute_bonus($slot_list)
{
    //TODO
        
    return array();
}

session_start();

include 'config.inc.php';

$dbh = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);

//SI y a des post d'auth?

/*if(isset($_POST['username']) && isset($_POST['password']))
{
    unset($_SESSION['user_id']);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname and user_type = 'validated'");        
    $stmt->bindParam(':uname', $_POST['username']);
	$stmt->execute();
    
	if( ($var = $stmt->fetch()) &&  $_POST['password'] == $var["password"])
	{
	    $_SESSION['user_id'] = $var["ID_Users"];
	}
	else
	{
	    $authError = "Echec de connexion";
	}
}*/

$_SESSION['user_id']          = '2'; //TODO erase me

//SI NOT est authentifié?
if (false)//!isset($_SESSION['user_id'])) 
{
        //on affiche le formulaire d'auth
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
        
        if(isset($authError))
        {
            echo '<H3>'.$authError.'</H3>';
        }
}
//SINON
else
{
    $timeslot_id_array = array(); //id des slots selectionnes par l'utilisateur
    $error_list        = array(); //liste des erreurs rencontrees
    $submitted_calendar = true;   //par defaut on empeche de poster quoi que ce soit
    
    //l'utilisateur a t'il deja valide son agenda ?
    $stmt = $dbh->prepare("SELECT * FROM User_Timeslot ut, Users u WHERE ut.ID_Users = u.ID_Users AND u.ID_Users = :user_id"); 
    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    $a = $stmt->fetchAll();

    if(count($a) == 0) //non
    {
        $submitted_calendar = false;
    }

    //SINON
    if(!$submitted_calendar && (array_key_exists("Test",$_POST) || array_key_exists("Envoyer",$_POST) )) // SI donnees de post
    {               
        //recuperer la liste des cases cochees
        if(array_key_exists("timeslot_id", $_POST))
        {
            $timeslot_id_array = $_POST["timeslot_id"];
        }
        
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
        for($i=0;$i<count($timeslot_id_array);$i++)
        {
            for($j=$i+1;$j<count($timeslot_id_array);$j++)
            {
                if(!(($selected_value[$j]["Start_time"] < $selected_value[$i]["Start_time"] && $selected_value[$j]["End_time"] <= $selected_value[$i]["Start_time"])
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
            }
        }
    }

//////// AFFICHAGES DES DONNEES ENREGISTREES ////////////////////////////////////////////////////////////////////////
    if($submitted_calendar) //l'utilisateur a deja enregistre son agenda
    {
        //on affiche son planning
        $stmt = $dbh->prepare("SELECT t.* FROM Timeslot t, User_Timeslot ut, Users u 
                                          WHERE t.ID_Timeslot  = ut.ID_Timeslot 
                                                AND u.ID_Users = ut.ID_Users
                                                AND u.ID_Users = :user_id
                                                ORDER BY t.Start_time ASC"); 

        $stmt->bindParam(":user_id",$_SESSION['user_id']);
        $stmt->execute();
        $a = $stmt->fetchAll();
        
        echo "<h2> Plages horaires </h2>";
        foreach($a as $key=>$value)
        {
            $datetime_start = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
            $datetime_end = fromMySQLDatetimeToPHPDatetime($value["End_time"]);
            
            echo "\"".$value["Description"]."\" ".$datetime_start->format("H:i")."-".$datetime_end->format("H:i")."<BR />";
        }
        
        //on affiche ses bonus
        echo "<h2> Bonus </h2>";
        $bonus = compute_bonus($a);
        foreach($bonus as $key=>$value)
        {
            echo $value."<BR />";
        }
        
    }
    else //l'utilisateur n'a pas encore enregistre son agenda
    {
//////// PREPARATION DES DONNEES ////////////////////////////////////////////////////////////////////////
    
        //recuperation des slots disponibles
        $stmt = $dbh->prepare("SELECT * FROM Timeslot t, Users u WHERE (SELECT count(*) FROM User_Timeslot ut 
                                                                               WHERE ut.ID_Timeslot = t.ID_Timeslot) < t.NumberOfPeople
                                                                 AND t.Reliability_needed <= u.Reliability AND u.ID_Users = :user_id"); 

        $stmt->bindParam(":user_id",$_SESSION['user_id']);
        $stmt->execute();
        $a = $stmt->fetchAll();

        //classement par jour, puis par activite
        //data pre parsing
        $selected_db_items = array();
        $sorted_data = array();
        $next_limit  = "2000-01-01 03:00:00";
        $current_day = array();

        foreach( $a as $key=>$value)
        {
            //on est toujours le jours courant ?
        	if($value["Start_time"] > $next_limit)
        	{
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
        	    $selected_db_items [] = $value;
        	}
        }

//////// CALCUL DES PLAGES QUI ONT DISPARUES (uniquement dans le cadre d'une validation) ///////////////////////////////////////////////

        //calculer si des plages ont disparues depuis les erreurs ou le test
        $first = true;
        $sql_param = "";
        $timeslot_id_array_unavailable = array();
        
        foreach($timeslot_id_array as $key=>$value)
        {
            $found = false;
            foreach( $a as $db_key=>$db_item)
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

//////// AFFICHAGE DES BONUS (uniquement dans le cadre d'une validation) ///////////////////////////////////////////
        
        $bonus = compute_bonus($selected_db_items);
        if(count($bonus) > 0)
        {
            echo "<h2> Bonus </h2>";
            foreach($bonus as $key=>$value)
            {
                echo $value."<BR />";
            }
        }

//////// AFFICHAGE DES ERREURS ////////////////////////////////////////////////////////////////////////

        //afficher les erreurs
        if(count($error_list) > 0)
        {
            echo "<h2>Erreur(s)</h2>";
            foreach($error_list as $index=>$error)
            {
                echo "<h3>".$error."</h3>";
            }
        }

//////// AFFICHAGE DU FORMULAIRE ////////////////////////////////////////////////////////////////////////

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

        		echo "<tr><td>".$desc."</td><td><table>";

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

        			$first_line  .= "<td><INPUT type=\"checkbox\" name=\"timeslot_id[]\" value=\"".$occurrence["ID_Timeslot"]."\" ".$checked." />".fromMySQLDatetimeToPHPDatetime($occurrence["Start_time"])->format("H:i")."-".fromMySQLDatetimeToPHPDatetime($occurrence["End_time"])->format("H:i")."</td>";
        		    $second_line .= "<td>".$occurrence["NumberOfPeople"]." place(s)</td>";
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
}
?>





