<?php session_start(); ?>
<html><head></head><body>
<?php
$debug = true;

include 'config.inc.php';
include 'function.inc.php';

try
{
    $dbh        = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //$dbh->query("SET GLOBAL TRANSACTION ISOLATION LEVEL SERIALIZABLE"); //TODO trouver une alternative, pas les droits de faire ça sur le site de marsinne...
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

$error_list = array(); //liste des erreurs rencontrees

//////////////// UTILS FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function buildSQLParam($array_size)
{
    $first = true;
    $sql_param = "";
    for($i = 0;$i<$array_size;$i++)
    {
        if($first)
        {
            $sql_param = ":param".($i+1);
            $first = false;
            continue;
        }
        $sql_param .= ",:param".($i+1);
    }
    return $sql_param;
}

//////////////// PROCESS FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


function sortData(&$error_list,$db_data, $timeslot_id_array)
{
    //classement par jour, puis par activite
    //data pre parsing
    $selected_db_items = array();
    $sorted_data = array();
    $next_limit  = "2000-01-01 03:00:00";
    $current_day = array();
    $still_available_slot = array(); //les slots que l'utilisateur peut encore voir dans la db

    //var_dump($timeslot_id_array);

    foreach( $db_data as $key=>$value)
    {
        //l'objet fait-il partie de ceux selectionne? (pour le calcul des bonus)
        if(in_array($value["ID_Timeslot"],$timeslot_id_array))
        {
            //on recupere l'id selectionne dans les slots recuperer dans la db, pour voir s'ils sont encore tous disponible
            $still_available_slot[] = $value["ID_Timeslot"];
            
            //l'objet selectionne est il encore disponible ?
            if($value["remaining"] == 0)
            {
                //add error, plage non disponible
                $error_list[] = "La plage ".getSlotDescriptionFromDBSlot($value)." n'est plus disponible"; 
                continue;
            }

            //on recupere l'objet selectionne encore disponible pour le calcul des bonus
            $selected_db_items[] = $value;//du coup la liste est filtree et ne contient plus de plage non disponible
        }
        
        //si la plage n'est pas disponible, on ne l'affiche plus
        if($value["remaining"] == 0)
        {
            continue;
        }
        
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
    }
    //echo count($current_day)."<BR />";
    
    $diff_slot_not_available = array_diff($timeslot_id_array, $still_available_slot);
    
    //s'il y a une difference ici, certains slot horraires ont ete supprime ou ont eu un changement de reliability
    if(count($diff_slot_not_available) > 0)
    {
        //indiquer le nombre de slot qui ne sont plus visible
        $error_list[] = "Certaines plages horaires ne sont plus disponible, adaptez votre horaire";
    }
    
    return array($sorted_data,$selected_db_items);
}

function isOverLap($dbh,&$error_list,$timeslot_id_array)
{
    if(count($timeslot_id_array) == 0)
    {return;}
    
    //verifier que les slots ne s'overlape pas
    //on recupere les valeurs des slots selectionnes
    
    $sql_param = buildSQLParam(count($timeslot_id_array));
    
    $sql_req = "SELECT Timeslot.* FROM Timeslot,Users 
                         WHERE ID_Timeslot IN (".$sql_param.")
                         AND Users.ID_Users = :user_id
                         AND Timeslot.Reliability_needed <= Users.Reliability";
    $stmt = $dbh->prepare($sql_req);
    
    for($i = 0;$i<count($timeslot_id_array);$i++)
    {
        $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
    }
    
    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    
    //echo $sql_req."<BR />";
    //echo count($timeslot_id_array)."<BR />";
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
                $error_list[] = "Conflit le ".getSlotStartDateFromDBSlot($selected_value[$i]).", la plage ".getSlotDescriptionFromDBSlot($selected_value[$i])." entre en conflit avec la plage ".getSlotDescriptionFromDBSlot($selected_value[$j]);
            }
        }
    }
}

function testData($dbh, &$error_list, $timeslot_id_array)
{
    global $AGENDA_MIN_SLOT, $AGENDA_MAX_SLOT;
    
    //verifier le nombre minimum de slot
    if($AGENDA_MIN_SLOT > count($timeslot_id_array))
    {
        $error_list[] = "Le nombre de slot minimum possible est de ".$AGENDA_MIN_SLOT;
        return false;
        //pas necessaire de faire de return false, ce n'est pas une erreur grave
    }
    
    //verifier le nombre maximal de slot
    if($AGENDA_MAX_SLOT < count($timeslot_id_array))
    {
        $error_list[] = "Le nombre de slot maximum possible est de ".$AGENDA_MAX_SLOT;
        return false; //evite d'afficher trop d'information d'overlap
    }
    
    //verifier l'overlap des slots
    if(isOverLap($dbh, $error_list, $timeslot_id_array))
    {
        return false;
    }
    
    //s'il y a la moindre erreur, on interrompt l'ajout
    if(count($error_list) > 0)
    {
        return false;
    }
    
    return true;
}

function recordData($dbh, &$error_list,$timeslot_id_array)
{         
    //on ajoute dans la DB
    if($dbh->beginTransaction())//transaction
    {
        //1) on verrouille, pas besoin transaction en serialisable
        
        //2) on verifie la disponibilite des plages
        $sql_param = buildSQLParam(count($timeslot_id_array));
        $sql_req = "SELECT Timeslot.Description, Timeslot.NumberOfPeople, Timeslot.NumberOfPeople - count(User_Timeslot.ID_Timeslot) AS remaining
                               FROM Timeslot LEFT JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot, Users
                               WHERE Timeslot.ID_Timeslot in (".$sql_param.")
                               AND Users.ID_Users = :user_id
                               AND Timeslot.Reliability_needed <= Users.Reliability
                               GROUP BY Timeslot.ID_Timeslot
                               FOR UPDATE";
        //echo $sql_req."<BR />";
        $stmt = $dbh->prepare($sql_req);
        
        for($i = 0;$i<count($timeslot_id_array);$i++)
        {
            $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
        }
        $stmt->bindParam(":user_id",$_SESSION['user_id']);
        
        $stmt->execute();
        $remain_slot = $stmt->fetchAll();
        
        //le nombre de slot disponible est il le meme que le nombre demandé (intervient dans un cas de changement de reliability/suppression de plage)
        if(count($timeslot_id_array) != count($remain_slot))
        {
            $error_list[] = "Certaines plages horaires ne sont plus disponible, adaptez votre horaire";
            $dbh->rollBack();
            return false;
        }
        
        $all_available = true;
        foreach($remain_slot as $index=>$value)
        {
            if($value["remaining"] == 0)
            {
                $all_available = false;
                break;
            }
        }
        
        if($all_available)
        {
            $first = true;
            $param_value = "";
            for($i = 0;$i<count($timeslot_id_array);$i++)
            {
                if($first)
                {
                    $param_value = "(:param".($i+1).", :user".($i+1).")";
                    $first = false;
                }
                else
                {
                    $param_value .= ",(:param".($i+1).", :user".($i+1).")";
                }
            }
            
            //3) on reserve les plages
            $SQL_request = "INSERT INTO User_Timeslot VALUES ".$param_value;
            echo $SQL_request."<BR />";
            $stmt = $dbh->prepare($SQL_request);
            
            for($i = 0;$i<count($timeslot_id_array);$i++)
            {
                echo ":param".($i+1)." ".$timeslot_id_array[$i]."<BR />";
                echo  ":user".($i+1)." ".$_SESSION['user_id']."<BR />";
                $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
                $stmt->bindParam( ":user".($i+1), $_SESSION['user_id']);
            }
            
            $stmt->execute();
            
            //4) on commit
            $dbh->commit();
            
            return true;
        }
        else
        {
            $error_list[] = "Certaines plages horaires ne sont plus disponible, adaptez votre horaire";
            $dbh->rollBack();
        }
    }
    else
    {
        $error_list[] = "Erreur le systeme ne g&egrave;re pas les transactions";
    }
    
    return false;
}

/*function findMissingSlot($dbh,&$error_list,$timeslot_id_array, $available_slot)
{    
    //on est obligé de partir des donnees que l'on a deja, 
        //si on refaisait une requete pour obtenir les slots manquants, on risquerait d'avoir une incoherence si une autre personne reserve entre les deux
    //TODO solution
        //on recupere tout les slots, meme les indisponibles avec le getAllAvailableSlot et on n'affiche que ceux necessaires
    
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
            $error_list[] = "La plage ".getSlotDescriptionFromDBSlot($value)." n'est plus disponible"; 
        }
    }
}
*/

function tryToAuth($dbh)
{   
    unset($_SESSION['user_id']);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname and user_type = 'validated' and password = :password");        
    $stmt->bindParam(':uname', $_POST['username']);
    $md5_mdp = md5($_POST['password']);
    $stmt->bindParam(':password', $md5_mdp);
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        $_SESSION['user_id'] = $var["ID_Users"];
    }
}

//////////////// GET DATA FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function getTimeslotID()
{
    if(array_key_exists("timeslot_id", $_POST))
    {
        $timeslot_id_array = $_POST["timeslot_id"];
        
        //on s'assure que l'on a bien recuperer un tableau
        if(! is_array($timeslot_id_array))
        {
            return array(); 
        }
        
        //on s'assure que l'ensemble de valeur du tableau sont de type numerique
        foreach($timeslot_id_array as $k=>$v)
        {
            if(!is_numeric($v))
            {
                return array(); 
            }
        }
        
        return $timeslot_id_array;
    }

    return array(); 
}

function getAllAvailableSlot($dbh)
{
    $sql_req = "SELECT Timeslot.*, Timeslot.NumberOfPeople - count(User_Timeslot.ID_Timeslot) AS remaining
                           FROM Timeslot LEFT JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot, Users
                           WHERE Users.ID_Users = :user_id
                           AND Users.user_type = 'validated'
                           AND Timeslot.Reliability_needed <= Users.Reliability
                           GROUP BY Timeslot.ID_Timeslot
                           ORDER BY Timeslot.Start_time ASC, Timeslot.Description ASC";
    
    /*$sql_req = "SELECT * FROM Timeslot t, Users u WHERE (SELECT count(*) FROM User_Timeslot ut 
                                                                           WHERE ut.ID_Timeslot = t.ID_Timeslot) < t.NumberOfPeople
                                                             AND t.Reliability_needed <= u.Reliability 
                                                             AND u.ID_Users = :user_id
                                                             AND u.user_type = 'validated'
                                                             ORDER BY t.Start_time ASC");*/
    $stmt = $dbh->prepare($sql_req);                                               
    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserSlot($dbh)
{
    $stmt = $dbh->prepare("SELECT t.* FROM Timeslot t, User_Timeslot ut, Users u 
                                      WHERE t.ID_Timeslot  = ut.ID_Timeslot 
                                            AND u.ID_Users = ut.ID_Users
                                            AND u.ID_Users = :user_id
                                            ORDER BY t.Start_time ASC"); 

    $stmt->bindParam(":user_id",$_SESSION['user_id']);
    $stmt->execute();
    return $stmt->fetchAll();
}

//////////////// PRINT FUNCTION //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function printForm($sorted_data, $timeslot_id_array)
{
    global $AGENDA_MIN_SLOT, $AGENDA_MAX_SLOT;
    
    
    echo "Nombre minimal de plages horaires pour valider un planning de b&eacute;n&eacute;vole : ".$AGENDA_MIN_SLOT."<BR />"
    ."Nombre maximale de plages horaires pour valider un planning de b&eacute;n&eacute;vole : ".$AGENDA_MAX_SLOT."<BR /><BR />"
    ."<form METHOD=\"POST\" ACTION=\"agenda.php\"><INPUT type=\"submit\" name=\"Test\" value=\"Test\">
    <INPUT type=\"submit\" name=\"Envoyer\" value=\"Envoyer\">";

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

function printAuthForm()
{
    ?>
    <form method="POST" action="./agenda.php">
            <table BORDER=0>
                <tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username"></td></tr>
                <tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password"></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td>                              <td><br /><INPUT type="submit" name="connect" value="Se connecter"></td></tr>
            </table>
        </form>
    <?php
}

function printUserSlot($user_slot)
{
    echo "<h2> Plages horaires </h2>";
    foreach($user_slot as $key=>$value)
    {
        echo getSlotDescriptionFromDBSlot($value)."<BR />";
    }
}

function printError(&$error_list)
{
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

function printBonus($dbh,$selected_db_items)
{
    $bonus = compute_bonus($dbh,$selected_db_items);
    if(count($bonus) > 0)
    {
        echo "<h2> Bonus </h2>";
        foreach($bonus as $key=>$value)
        {
            echo $value["sum"]." fois : ".$value["Description"]."<BR />";
        }
    }
}

function printBonusTable($dbh)
{
    //description, threshold, cat
    $stmt = $dbh->prepare(" SELECT Bonus_cat.Description as descr, Bonus.threshold as thres, Bonus_items_bonus.bonus_count as count, Bonus_items.Description as itemdescr
                            FROM Bonus_cat, Bonus, Bonus_items_bonus, Bonus_items
                            WHERE Bonus.cat = Bonus_cat.ID_Bonus_cat
                              AND Bonus.ID_Bonus = Bonus_items_bonus.ID_Bonus
                              AND Bonus_items_bonus.ID_Bonus_item = Bonus_items.ID_Bonus_item
                            ORDER BY Bonus_cat.Description ASC, Bonus.threshold ASC");
    
    //$stmt = $dbh->prepare("SELECT * FROM Bonus INNER JOIN Bonus_cat on Bonus.cat = Bonus_cat.ID_Bonus_cat  ORDER BY Bonus.cat ASC, Bonus.threshold ASC"); 
    $stmt->execute();
    $a = $stmt->fetchAll();
    
    if(count($a) == 0)
    {
        return;
    }
    echo "<h2> Table des Bonus </h2>";
    
    $previous_id_cat = "";
    $previous_thres = -1;
    $first = true;
    $first_thres = true;
    $first_line = "<tr>";
    $second_line = "<tr>";
    foreach($a as $k=>$v)
    {
        if($v["descr"] != $previous_id_cat)
        {
            if(!$first)
            {
                echo $first_line."</tr>".$second_line."</tr>"."</table><BR />";
            }
            else
            {
                $first = false;
            }
            echo $v["descr"]." : ";
            echo "<table border=\"1\">";
            $first_line = "<tr>";
            $second_line = "<tr>";
            $previous_id_cat = $v["descr"];
        }
        
        if($previous_thres != $v["thres"])
        {
            if($first_thres)
            {
                $first_thres = false;
            }
            else
            {
                $first_line .= "</td>";
            }
            
            $first_line .= "<td>".$v["count"]." * ".$v["itemdescr"];
            $second_line .= "<td>".$v["thres"]." plage(s) horaire(s) requise(s)</td>";
            
            $previous_thres = $v["thres"];
        }
        else
        {
            $first_line .= "<BR />".$v["count"]." * ".$v["itemdescr"];
        }
        
        
    }
    
    if(!$first)
    {
        if(!$first_thres)
        {
            echo $first_line."</td></tr>".$second_line."</tr>"."</table>";
        }
        else
        {
            echo $first_line."</tr>".$second_line."</tr>"."</table>";
        }
    }    
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if(array_key_exists("Deconnection",$_POST))
{
    unset($_SESSION['user_id']);
}

//////////////// AUTH ///////////////////////////////////////////////////
$try_to_auth = false;

try
{
    if(array_key_exists("username",$_POST) && array_key_exists("password",$_POST) && array_key_exists("connect",$_POST))
    {
        tryToAuth($dbh);
        $try_to_auth = true;
    }

    if (!isset($_SESSION['user_id']))//est-on authentifie?
    //////////////// NOT AUTHENTICATED PART //////////////////////////////////////////////////////////////////////////////////////////////////////////
    {
        //on affiche le formulaire d'auth
        printAuthForm();
        
        if($try_to_auth)
        {
            echo '<H3>Echec de connexion, le nom d\'utilisateur ou le mot de passe sont peu &ecirc;tre incorrects.  
            Le compte n\'a peu &ecirc;tre pas encore &eacute;t&eacute; valid&eacute; par un administrateur.  Si le probl&egrave;me persiste, contactez un administrateur: webmaster@folkfestivalmarsinne.be</H3>';
        }
    }
    //////////////// AUTHENTICATED PART /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    else
    {
        echo "<form METHOD=\"POST\" ACTION=\"agenda.php\"><INPUT type=\"submit\" name=\"Deconnection\" value=\"Deconnection\"></form>";
        
        //////////////// GET DATA ///////////////////////////////////////////////////

        $timeslot_id_array = getTimeslotID(); //RECUPERATION DE LA LISTE DES CASES COCHEE, id des slots selectionnes par l'utilisateur (s'il y en a)
        $user_slot = getUserSlot($dbh);//on recupere les slots que l'utilisateur a reserve 
        $submitted_calendar = (count($user_slot) > 0);//est ce que l'utilisateur a deja reserve ?
        
        //////////////// RECORD/TEST ///////////////////////////////////////////////////

        if(! $submitted_calendar)
        {
            if(array_key_exists("Test",$_POST))
            {
                testData($dbh,$error_list,$timeslot_id_array);
            }
            else if(array_key_exists("Envoyer",$_POST) )
            {
                if(testData($dbh,$error_list,$timeslot_id_array))
                {
                    $submitted_calendar = recordData($dbh,$error_list,$timeslot_id_array); //on essaye d'enregistrer ou de tester les donnees
                }
            }
        }

        //////////////// PRINT /////////////////////////////////////////////////////////

        if($submitted_calendar) //l'utilisateur a deja enregistre son agenda
        {        
            printUserSlot($user_slot); //on affiche son planning
            printBonus($dbh,$user_slot);    //on affiche ses bonus     
        }
        else //l'utilisateur n'a pas encore enregistre son agenda
        {
            $available_slot = getAllAvailableSlot($dbh); //on recupere les slots horaire encore disponible        
            list($sorted_data,$selected_db_items) = sortData($error_list,$available_slot, $timeslot_id_array); //on trie les donn&eacute;es par jour et par event
            //findMissingSlot($dbh,$error_list, $timeslot_id_array, $available_slot); //identification des plages qui aurait disparue depuis le pr&eacute;c&eacute;dent test
            
            if(count($error_list) == 0)//si erreur, on affiche pas les bonus
            {
                printBonus($dbh,$selected_db_items); //affichage des bonus, s'il y en a
            }
            
            printError($error_list); //on affiche les erreurs, s'il y en a
            echo "<BR />";
            printForm($sorted_data, $timeslot_id_array); //affichage du formulaire permettant de choisir ses slots
            printBonusTable($dbh); //on affiche comment obtenir les bonus
            echo "<BR />";
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
</body></html>




