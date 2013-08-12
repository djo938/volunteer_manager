<?php session_start(); ?>
<html><head></head><body>
<?php
//set_error_handler("var_dump");

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

//////////////// PROCESS FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function genKey($ar)
{
    $key = "";
    foreach($ar as $k=>$v)
    {
        $key .= "_".$k."_".$v;
    }
    return $key;
}

/*
 * return true if slot match 
 */
function checker($slot, $to_check)
{
    foreach($to_check as $key=>$value)
    {
        if($key == "Start_time")
        {
            if( !array_key_exists("Start_time",$slot)){continue;}
            if($slot["Start_time"] < $value){return false;}
        }
        else if($key == "End_time") //seul la date de debut doit etre dans le range
        {
            if( !array_key_exists("Start_time",$slot)){continue;}
            if($slot["Start_time"] > $value){return false;}
        }
        else
        {
            if( !array_key_exists($key,$slot)){continue;}
            
            if($slot[$key] != $value) 
            {
                return false;
            }
        }
    }
    return true;
}

function getCounter($to_check, $key, $lifetime)
{    
    return function ($slot, &$error_list, &$accumulator) use ($to_check, $key, $lifetime) 
            {
                //check if the slot match with the cond
                if(!checker($slot,$to_check)){return true;}
            
                //is it the first slot met ?
                if( ! array_key_exists($key, $accumulator) ) {$accumulator[$key] = array();}
            
                $subkey = "";
                if($condition["Lifetime"] == "parPlage")     {$subkey = $slot["Description"];}
                else if($condition["Lifetime"] == "parJour")
                {
                    $slot_date = fromMySQLDatetimeToPHPDatetime($slot["Start_time"]);

                    if($slot_date->format("H:i") <= "03:00")
                    {
                        $slot_date->sub(new DateInterval("P1D"));
                    }

                    $subkey = $slot_date->format("l d F");
                }//else {} //pour tout l'event
            
                if( ! array_key_exists($subkey, $accumulator[$key]) ) {$accumulator[$key][$subkey] = 0;}
            
                //increment the counter value
                $accumulator[$key][$subkey] += 1;            
                return true;
            };
}

function getFilter($to_check)
{
    return function ($slot, &$error_list, &$accumulator) use ($to_check) 
            {
                //check if the slot match with the cond
                if(!checker($slot,$to_check)){return true;}
            
                return false;
            };
}

function getAllCorrespondingSlotLister($to_check, $key, $lifetime)
{
    return function ($slot, &$error_list, &$accumulator) use ($to_check, $key, $lifetime) 
           {
               //check if the slot match with the cond
               if(!checker($slot,$to_check)){return true;}
           
               //is it the first slot met ?
               if( ! array_key_exists($key, $accumulator) ) {$accumulator[$key] = array();}
           
               $subkey = "";
               if($lifetime == "parPlage")     {$subkey = $slot["Description"];}
               else if($lifetime == "parJour")
               {
                   $slot_date = fromMySQLDatetimeToPHPDatetime($slot["Start_time"]);

                   if($slot_date->format("H:i") < "03:00")
                   {
                       $slot_date->sub(new DateInterval("P1D"));
                   }

                   $subkey = $slot_date->format("l d F");
               }//else {} //pour tout l'event
           
               if( ! array_key_exists($subkey, $accumulator[$key]) ) {$accumulator[$key][$subkey] = array();}
           
               //add the corresponding slot
               $accumulator[$key][$subkey] [$slot["Start_time"]] = $slot;            
               return true;
           };
}

//TODO
    //-le compteur de jours ne compte qu'avec les jours qu'il rencontre
        //solution : prendre le premier et dernier jour dans la db
            //quand fromTo, on prend tout les jours dans le range
    //-ne peut pas etre appel&eacute; s'il y a des overlap
        //pour le post du moins, puisqu'en pre il n'y a aucun filtrage
    //faire une categorie pres/post
    
function checkCondition($dbh,&$error_list,$allSlot, $type = "pre")
{
    //get All Pre Condition
    $sql_req = "SELECT * FROM `Condition` WHERE Type = '".$type."' OR Type = 'pre/post'";
    $stmt = $dbh->prepare($sql_req);
    $stmt->execute();
    $conditions = $stmt->fetchAll();
    
    //init function list
    $function_list = array();
    $function_post = array();
    
    //append function
    foreach($conditions as $k=>$condition)
    {
        //build target type
        $to_check = array();
        //$to_check["Parameter"] = $condition["Parameter"];
        
        if($condition["Type"] == "pre")
        {
            $pre = true;
            $post = false;
        }
        else if($condition["Type"] == "pre")
        {
            $pre = false;
            $post = true;
        }
        else{$pre = $post = true;}
        
        //CONSTRUCTION DE LA SELECTION
        //CIBLE DU FILTRAGE
        $target_type ="";
        if     ($condition["Target_type"] == "Timeslot_description") 
        {
            $to_check["Description"] = $condition["Target"];
            $target_type = "Description";
        }
        else if($condition["Target_type"] == "Timeslot_Bonus_cat")   
        {
            $to_check["Bonus_cat"] = $condition["Target"];
            $target_type = "Bonus_cat";
        }
        else if($condition["Target_type"] == "FromTo")//decoupe de la condition "2000-01-01 03:00:00|2000-01-01 03:00:00"     
        {
            $part = explode("|",$condition["Target"]);
            $to_check["Start_time"] = $part[0];
            $to_check["End_time"] = $part[1];
        } //else if($condition["Target_type"] == "all") {} //nothing to check

        //SCOPE DU FILTRAGE
        if($condition["Scope"] == "local")
        {
            $to_check["ID_Users"] = $_SESSION['user_id'];
        }
        
        $key      = genKey($to_check).genKey($condition); //ajouter le nom de la fonction, des parametres, etc.. +une un int random ?
        $limit    = $condition["Parameter"];
        $desc     = $condition["Description"];
        $lifetime = $condition["Lifetime"];
        $target   = $condition["Target"];
        
        switch($condition["Condition"])
        {
            case "min": //au min N plage de type TARGET
                $function_list [] = getCounter($to_check, $key, $lifetime);
                $function_post [] = function(&$error_list, &$accumulator) use ($limit,$key, $desc, $lifetime)
                                    {
                                        if(!array_key_exists($key, $accumulator)){return;}
                                        
                                        foreach($accumulator[$key] as $k=>$counter)
                                        {
                                            if($counter < $limit) 
                                            {
                                                if     ($lifetime == "parPlage") {$error_list [] = $desc." pour la plage : ".$k;}
                                                else if($lifetime == "parJour")  {$error_list [] = $desc." pour le jour : ".$k;}
                                                else                             {$error_list [] = $desc;}
                                            }
                                        }
                                    };
                break;

            case "max": //au max N plage de type TARGET
                $function_list [] = getCounter($to_check, $key, $lifetime);
                $function_post [] = function(&$error_list, &$accumulator) use ($limit,$key, $desc, $lifetime)
                                    {
                                        if(!array_key_exists($key, $accumulator)){return;}
                                        
                                        foreach($accumulator[$key] as $k=>$counter)
                                        {
                                            if($counter > $limit) 
                                            {
                                                if     ($lifetime == "parPlage") {$error_list [] = $desc." pour la plage : ".$k;}
                                                else if($lifetime == "parJour")  {$error_list [] = $desc." pour le jour : ".$k;}
                                                else                             {$error_list [] = $desc;}
                                            }
                                        }
                                    };
                break;

            case "minConsecutive"; //au max N plage consecutive de type TARGET
                //make a list of all targeted slot
                $function_list [] = getAllCorrespondingSlotLister($to_check, $key, $lifetime);
                $function_post [] = function(&$error_list, &$accumulator) use ($limit,$key, $desc, $lifetime)
                                    {
                                        if(!array_key_exists($key, $accumulator)){return;}
                                        
                                        foreach($accumulator[$key] as $k=>$slot_list)
                                        {
                                            //sort by start_date
                                            ksort($slot_list);
                                            
                                            //check consecutive
                                            $count = 1;
                                            $previous_end_date = "2000-01-01 03:00:00";
                                            $erreur_found = false;
                                            $start = true;
                                            
                                            foreach($slot_list as $kstart => $slot)
                                            {
                                                if($slot["Start_time"] == $previous_end_date){$count +=1;}
                                                else
                                                {
                                                    if(! $start)
                                                    {
                                                        if($count < $limit)
                                                        {
                                                            $erreur_found = true;
                                                            break;
                                                        }
                                                    }
                                                    $start = false;
                                                    $count = 1;
                                                }
                                                
                                                $previous_end_date = $slot["End_time"];
                                            }
                                            
                                            if($erreur_found) //is consecutive ?
                                            {
                                                if     ($lifetime == "parPlage") {$error_list [] = $desc." pour la plage : ".$k;}
                                                else if($lifetime == "parJour")  {$error_list [] = $desc." pour le jour : ".$k;}
                                                else                             {$error_list [] = $desc;}
                                            }
                                        }
                                    };
                break;

            case "maxConsecutive": //au min N plage consecutive de type TARGET
                $function_list [] = getAllCorrespondingSlotLister($to_check, $key, $lifetime);
                $function_post [] = function(&$error_list, &$accumulator) use ($limit,$key, $desc, $lifetime)
                                {
                                    if(!array_key_exists($key, $accumulator)){return;}
                                    
                                    foreach($accumulator[$key] as $k=>$slot_list)
                                    {
                                        //sort by start_date
                                        ksort($slot_list);
                                        
                                        //check consecutive
                                        $count = 1;
                                        $previous_end_date = "2000-01-01 03:00:00";
                                        $erreur_found = false;
                                        foreach($slot_list as $start => $slot)
                                        {                                            
                                            if($slot["Start_time"] == $previous_end_date){$count +=1;}
                                            else                                        {$count = 1;}
                                            
                                            if($count > $limit)
                                            {
                                                $erreur_found = true;
                                                break;
                                            }
                                            
                                            $previous_end_date = $slot["End_time"];
                                        }
                                        
                                        if($erreur_found) //is consecutive ?
                                        {
                                            if     ($lifetime == "parPlage") {$error_list [] = $desc." pour la plage : ".$k;}
                                            else if($lifetime == "parJour")  {$error_list [] = $desc." pour le jour : ".$k;}
                                            else                             {$error_list [] = $desc;}
                                        }
                                    }
                                };
                break;

            //case "minUser": //au min N utilisateur pour la plage de type TARGET
            //    break;

            case "maxUser";
                $group_by = "";
                
                if($condition["Lifetime"] == "parPlage")     
                {
                    $group_by = " GROUP BY Timeslot.Description";
                    $sql_req = "SELECT COUNT(*) as total_count, Timeslot.Description as Des
                                FROM Timeslot INNER JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot ";
                }
                else if($condition["Lifetime"] == "parJour")
                {
                    $group_by = " GROUP BY DATE(DATE_SUB(Timeslot.Start_time, INTERVAL 3 HOUR))";
                    $sql_req = "SELECT COUNT(*) as total_count, DATE(DATE_SUB(Timeslot.Start_time, INTERVAL 3 HOUR)) as Dat
                                FROM Timeslot INNER JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot ";
                }
                else
                {
                    $sql_req = "SELECT COUNT(*) as total_count 
                                FROM Timeslot INNER JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot ";                    
                }
                
                $first = true;
                $i = 0;
                foreach($to_check as $key=>$value)
                {
                    if($first)
                    {
                        $sql_req .= " WHERE ";
                        $first = false;
                    }
                    else
                    {
                        $sql_req .= " AND ";
                    }
                    
                    if($key == "Start_time")    {$sql_req .= "Start_time > :value".$i;}
                    else if($key == "End_time") {$sql_req .= "Start_time < :value".$i;}
                    else                        {$sql_req .= $key." = :value".$i;}
                    $i += 1;
                }
                $sql_req .= $group_by;
                
                $stmt = $dbh->prepare($sql_req); 
                $i = 0;
                foreach($to_check as $key=>$value)
                {
                    $stmt->bindParam(":value".$i,$value);
                    $i += 1;
                }
                //echo $sql_req."<BR />";                                          
                $stmt->execute();
                $data_to_check = $stmt->fetchAll();
                foreach($data_to_check as $k=>$var)
                {
                    if($condition["Lifetime"] == "parPlage")     
                    {
                        $to_check["Description"] = $var["Des"];
                    }
                    else if($condition["Lifetime"] == "parJour")
                    {
                        $date = DateTime::createFromFormat('Y-m-d', $var["Dat"]);
                        $date->setTime(3,0,1);
                        $to_check["Start_time"] = $date->format("Y-m-d H:i:s");
                    
                        $date->add(new DateInterval("P1D"));
                        $date->setTime(3,0,0);
                        $to_check["End_time"] = $date->format("Y-m-d H:i:s");
                    }
                    
                    //on depasse la limite ?
                    if($pre)
                    {
                        if($var["total_count"] > $limit)
                        {
                            $function_list [] = getFilter($to_check);
                        }
                    }
                    
                    
                }
                
                if($post)
                {
                    //SI POST
                        //TODO compter le nombre d'occurence pour les slots choisi

                        //TODO faire la somme avec celle deja existante

                        //TODO generer une erreur pour indiquer le nombre de place restante
                }
                
                break;

            case "IfNOnlyM"; //plutot if N, not M
                    // $limit is N
                    // $limit2 is Target
                    // on se limite sur quoi ? Target_type
                    //la lifetime n'a pas de raison d'etre ici
                        //TODO, quoi que..., le lifetime par jour pourrait etre interessante
                            //example, X VS Y , jour 1, on fait X, jour 2, on fait Y, mais on ne peut pas faire les 2 activites le meme jour
                            //pas possible de faire ça avec la limite actuelle
                    //$target_type FromTo, n'est pas applicable, car le parameter N est de type int
                        //TODO sauf si on le convertit en date a partir d'un timestamp
                                        
                    //make a list with every if of type Target_type
                    if($target_type != "")
                    {                        
                        $function_list [] = function ($slot, &$error_list, &$accumulator) use ($to_check, $key, $target_type) 
                                                   {
                                                       //TODO corriger ce sale hack !!!!!!!!!
                                                            //ça ne comptabilise pas le montage pour ne pas entrer en conflit avec la regle demontage VS weekend...
                                                       if($slot["Description"] == "Montage"){return true;}
                                                       
                                                       //if(!checker($slot,$to_check)){return true;}
                                                       //TODO check if keys exists
                                                       $accumulator[$key][$slot[$target_type]] = true;
                                                       
                                                       return true;
                                                   };
                        //N is in list ?
                        $function_post [] = function(&$error_list, &$accumulator) use ($limit,$key, $desc, $target)
                                            {
                                                if(!array_key_exists($key, $accumulator)){return;}
  
                                                if(array_key_exists(intval($limit), $accumulator[$key]) && 
                                                   array_key_exists(intval($target), $accumulator[$key]))
                                                {//if yes, M can't be in the list
                                                    $error_list [] = $desc;
                                                }
                                            };
                    }
                break;
        }
    }
    
    //counter an live check
    $filteredSlot = array();
    $accumulator = array();
    foreach($allSlot as $kslot=>$slot)
    {
        $add = true;
        foreach($function_list as $k=>$function)
        {
            if(! $function($slot, $error_list, $accumulator) )
            {
                $add = false;
                break;
            }
        }
        
        if($add)
        {
            $filteredSlot [] = $slot;
        }
    }
    
    //post check
    foreach($function_post as $k=>$fun)
    {
        $fun($error_list, $accumulator);
    }
    
    return $filteredSlot;
}

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
            ksort($current_day);
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
            $sorted_data[formatFrench($start_day->format("l d F"))] = &$current_day;

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
    
    //on trie les activites du dernier jour (au pire le tableau est vide)
    ksort($current_day);

    //s'il y a une difference ici, certains slot horraires ont ete supprime ou ont eu un changement de reliability
    $diff_slot_not_available = array_diff($timeslot_id_array, $still_available_slot);
    if(count($diff_slot_not_available) > 0)
    {
        //indiquer le nombre de slot qui ne sont plus visible
        $error_list[] = "Certaines plages horaires ne sont plus disponible, adaptez votre horaire";
    }
    
    //sort on keyname    
    return array($sorted_data,$selected_db_items);
}

function isOverLap($dbh,&$error_list,$selected_value)
{
    
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
    if($AGENDA_MIN_SLOT > count($timeslot_id_array))  //TODO bouger ça dans les tests pre/post
    {
        $error_list[] = "Le nombre de slot minimum possible est de ".$AGENDA_MIN_SLOT;
        return false;
        //pas necessaire de faire de return false, ce n'est pas une erreur grave
    }
    
    //verifier le nombre maximal de slot
    if($AGENDA_MAX_SLOT < count($timeslot_id_array))//TODO bouger ça dans les tests pre/post
    {
        $error_list[] = "Le nombre de slot maximum possible est de ".$AGENDA_MAX_SLOT;
        return false; //evite d'afficher trop d'information d'overlap
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////

    //get selected slot
    $selected_value = getSelectedSlot($dbh,$timeslot_id_array);
    
    //verifier l'overlap des slots
    if(isOverLap($dbh, $error_list, $selected_value))
    {
        return false;
    }
    
    checkCondition($dbh,$error_list,$selected_value, "post");
    
    //s'il y a la moindre erreur, on interrompt l'ajout
    if(count($error_list) > 0)
    {
        return false;
    }
    
    return true;
}

function recordData($dbh, &$error_list,$timeslot_id_array)
{   
    //lock the database
    for($i=0; $i < 3; $i++)
    {
        $statement = $dbh->query("SELECT GET_LOCK('lock1',10) as lock_result");
        $result = $statement->fetch();
    
        if(count($result) == 0)
        {
            $error_list [] = "Echec de verouillage de la base de donn&eacute;es (1)";
            return false;
        }
        
        if($result["lock_result"] == 0)//timeout
        {
            continue;
        }
        
        if($result["lock_result"] == 1)//success
        {
            break;
        }
        
        //erreur
        $error_list [] = "Echec de verouillage de la base de donn&eacute;es (2)";
        return false;
    }
    //echo "i = ".$i."<BR />";
    if($i == 3)
    {
        $error_list [] = "Echec de verouillage de la base de donn&eacute;es (3)";
        return false;
    }
    
    //on ajoute dans la DB
    //if($dbh->beginTransaction())//transaction
    //{
        //1) on verrouille, pas besoin transaction en serialisable
        
        //2) on verifie la disponibilite des plages
        $sql_param = buildSQLParam(count($timeslot_id_array));
        $sql_req = "SELECT Timeslot.Description, Timeslot.NumberOfPeople, Timeslot.NumberOfPeople - count(User_Timeslot.ID_Timeslot) AS remaining
                               FROM Timeslot LEFT JOIN User_Timeslot ON Timeslot.ID_Timeslot = User_Timeslot.ID_Timeslot, Users
                               WHERE Timeslot.ID_Timeslot in (".$sql_param.")
                               AND Users.ID_Users = :user_id
                               AND Timeslot.Reliability_needed <= Users.Reliability
                               GROUP BY Timeslot.ID_Timeslot";
                               //FOR UPDATE";
        //echo $sql_req."<BR />";
        $stmt = $dbh->prepare($sql_req);
        
        for($i = 0;$i<count($timeslot_id_array);$i++)
        {
            $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
        }
        $stmt->bindParam(":user_id",$_SESSION['user_id']);
        
        $stmt->execute();
        $remain_slot = $stmt->fetchAll();
        
        /*if($_SESSION['user_id'] == 10)
        {
            sleep(20);
        }*/
        
        //le nombre de slot disponible est il le meme que le nombre demand&eacute; (intervient dans un cas de changement de reliability/suppression de plage)
        if(count($timeslot_id_array) != count($remain_slot))
        {
            $error_list[] = "Echec de l'enregistrement (1)"; //un message plus precis sera calcule plus loin
            //$dbh->rollBack();
            $dbh->query("SELECT RELEASE_LOCK('lock1') as lock_result");
            return false;
        }
        
        //tous les slots sont ils disponible ?
        foreach($remain_slot as $index=>$value)
        {
            if($value["remaining"] == 0)
            {
                $error_list[] = "Echec de l'enregistrement (2)"; //un message plus precis sera calcule plus loin
                //$dbh->rollBack();
                $dbh->query("SELECT RELEASE_LOCK('lock1') as lock_result");
                return false;
            }
        }
        
        $first = true;
        $param_value = "";
        for($i = 0;$i<count($timeslot_id_array);$i++)
        {
            if($first)
            {
                $param_value = "(:param".($i+1).", :user".($i+1).", NOW())";
                $first = false;
            }
            else
            {
                $param_value .= ",(:param".($i+1).", :user".($i+1).", NOW())";
            }
        }
        
        //3) on reserve les plages
        $SQL_request = "INSERT INTO User_Timeslot VALUES ".$param_value;
        //echo $SQL_request."<BR />";
        $stmt = $dbh->prepare($SQL_request);
        
        for($i = 0;$i<count($timeslot_id_array);$i++)
        {
            //echo ":param".($i+1)." ".$timeslot_id_array[$i]."<BR />";
            //echo  ":user".($i+1)." ".$_SESSION['user_id']."<BR />";
            $stmt->bindParam(":param".($i+1), $timeslot_id_array[$i]);
            $stmt->bindParam( ":user".($i+1), $_SESSION['user_id']);
        }
        
        $stmt->execute();
        
        //4) on commit
        //$dbh->commit();
        $dbh->query("SELECT RELEASE_LOCK('lock1') as lock_result");
        return true;

    /*}
    else
    {
        $error_list[] = "Erreur le systeme ne g&egrave;re pas les transactions";
    }*/

}

//////////////// GET DATA FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function getSelectedSlot($dbh,$timeslot_id_array)
{
    if(count($timeslot_id_array) == 0) {return array();}
    
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
    
    $stmt->execute();
    return $stmt->fetchAll();
}

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
                           ORDER BY Timeslot.Start_time ASC";
    
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

function printUserSlot($user_slot)
{
    echo "<h2> Plages horaires </h2>";
    $limit_day = "";
    foreach($user_slot as $key=>$value)
    {
        if($limit_day < $value["Start_time"])
        {
            echo "<BR />";
            $limit_day = buildLimitFromDBDatetime($value["Start_time"]);
            $day_date = fromMySQLDatetimeToPHPDatetime($limit_day);
            
            $day_date->sub(new DateInterval("P1D"));
            echo formatFrench($day_date->format("l d F"))."<BR />";
        }
        
        echo "&nbsp;&nbsp;&nbsp;".getSlotDescriptionFromDBSlot($value)."<BR />";
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

function printBonus($dbh,$selected_db_items, $printzero)
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
    else 
    {
        if($printzero)
        {
            echo "<h2> Bonus </h2>";
            echo "Pas de bonus<BR />";
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
                            ORDER BY Bonus_cat.order ASC, Bonus.threshold ASC");
    
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

function printSlotDescription()
{
    echo "<h2> Description des plages horaires </h2><table border=\"1\">
    <tr><td>Contr&ocirc;le parking artisans/VIP/Camping</td>
                <td>La t&acirc;che du b&eacute;n&eacute;vole sera de contr&ocirc;ler l'acc&egrave;s au parking artisans/VIP et de n'y laisser entrer que les personnes et voitures munies d'une autorisation fournie par le festival. De plus, il devra g&eacute;rer au mieux la r&eacute;partition des voitures et veiller &agrave; ce que les conducteurs se parquent de la mani&egrave;re la plus efficace possible afin de garantir une bonne gestion de l'espace parking.</td></tr>

    <tr><td>Contr&ocirc;le parking public</td>
                <td>Le b&eacute;n&eacute;vole devra uniquement g&eacute;rer au mieux quant &agrave; la r&eacute;partition des voitures et veiller &agrave; ce que les conducteurs se parquent de la mani&egrave;re la plus efficace possible afin de garantir une bonne gestion de l'espace parking.</td></tr>

    <tr><td>Contr&ocirc;le entr&eacute;es</td>
                <td>Le b&eacute;n&eacute;vole devra contr&ocirc;ler le bracelet de chaque personne entrant sur le site. Toute personne n'en &eacute;tant pas munie, quelle qu'en soit la raison, ne pourra pas acc&eacute;der au festival.</td></tr>

    <tr><td>Contr&ocirc;le feu de camp</td>
                <td>Le b&eacute;n&eacute;vole devra apporter le mat&eacute;riel n&eacute;cessaire &agrave; la r&eacute;alisation s&eacute;curis&eacute;e du feu de camp dans le camping ainsi que l'allumer &agrave; l'endroit pr&eacute;vu vers une heure du matin.</td></tr>

    <tr><td>Propret&eacute; du site</td>
                <td>Le b&eacute;n&eacute;vole devra veiller &agrave; la propret&eacute; du site, &agrave; savoir circuler et trier tout d&eacute;tritus pr&eacute;sent sur le site, vider les poubelles, passer un coup de balais dans la salle et le bar s'il est inscrit en d&eacute;but de journ&eacute;e, vider &agrave; intervalle r&eacute;guliers les toilettes s&egrave;ches du camping, mettre des rouleaux de papier dans les WC (salle et wc sec) et veiller &agrave; leur propret&eacute;.</td></tr>

    <tr><td>Accueil VIP, voisins, divers</td>
                <td>Un stand sera install&eacute; &agrave; l'entr&eacute;e du festival afin de servir de point d'informations pour les festivaliers. Le b&eacute;n&eacute;vole devra accueillir toute personne s'y pr&eacute;sentant et l'aiguiller vers la personne ou l'endroit n&eacute;cessaire. Une connaissance du festival est obligatoire afin d'acc&eacute;der &agrave; ce poste.</td></tr>

    <tr><td>Caisse vente entr&eacute;es</td>
                <td>Le b&eacute;n&eacute;vole devra vendre les entr&eacute;es aux festivaliers n'ayant pas achet&eacute; de pr&eacute;vente. Ce poste n&eacute;cessitant la manipulation d'argent, il n'est possible d'y avoir acc&egrave;s que si vous avez &eacute;t&eacute; b&eacute;n&eacute;vole durant l'&eacute;dition 2012 du festival.</td></tr>

    <tr><td>Pr&eacute;ventes</td>
                <td>Le b&eacute;n&eacute;vole devra fournir les entr&eacute;es aux festivaliers ayant achet&eacute; des pr&eacute;ventes &agrave; l'aide d'une liste qui lui sera fournie.</td></tr>

    <tr><td>Bar</td>
                <td>Le b&eacute;n&eacute;vole devra g&eacute;rer le bar sous la gestion du responsable bar. Cela inclu de servir les boissons, de faire de temps en temps des tours en salle pour r&eacute;cup&eacute;rer la vaisselle, de faire la vaisselle et de temps en temps changer de f&ucirc;t. Il est n&eacute;cessaire d'avoir d&eacute;j&agrave; servi dans un bar auparavant pour acc&eacute;der &agrave; ce poste. Attention, il est plus que d&eacute;conseill&eacute; de prester deux heures au bar d'affil&eacute;e.</td></tr>

    <tr><td>Caisse jetons bar</td>
                <td>Une caisse sera tenue par deux b&eacute;n&eacute;voles charg&eacute;s de vendre les jetons permettant d'acheter des consommations au bar. Il faudra &eacute;galement g&eacute;rer les jetons-caution pour les verres. Ce poste n&eacute;cessitant la manipulation d'argent, il n'est possible d'y avoir acc&egrave;s que si vous avez &eacute;t&eacute; b&eacute;n&eacute;vole durant l'&eacute;dition 2012 du festival.</td></tr>

    <tr><td>Confection et vente de sandwiches</td>
                <td>Des sandwiches seront propos&eacute;s &agrave; la vente aux festivaliers les samedi et dimanche. Deux b&eacute;n&eacute;voles devront les confectionner pour ensuite les vendre. Ce poste n&eacute;cessitant la manipulation de tickets, il n'est possible d'y avoir acc&egrave;s que si vous avez &eacute;t&eacute; b&eacute;n&eacute;vole durant l'&eacute;dition 2012 du festival.</td></tr>

    <tr><td>(D&eacute;)Montage stand exposants</td>
                <td>Certains exposants ont besoin d'aide afin de mettre en place leur &eacute;tal le samedi ou pour le d&eacute;monter le dimanche. Une petite &eacute;quipe de b&eacute;n&eacute;voles sera charg&eacute;e de les aider.</td></tr>
    </table>";
                
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
        tryToAuth($dbh,'validated','user_id');
        $try_to_auth = true;
    }

    if (!isset($_SESSION['user_id']))//est-on authentifie?
    //////////////// NOT AUTHENTICATED PART //////////////////////////////////////////////////////////////////////////////////////////////////////////
    {
        //on affiche le formulaire d'auth
        printAuthForm("agenda.php");
        
        if($try_to_auth)
        {
            echo '<H3>Echec de connexion, le nom d\'utilisateur ou le mot de passe sont peut &ecirc;tre incorrects.  
            Le compte n\'a peu &ecirc;tre pas encore &eacute;t&eacute; valid&eacute; par un administrateur.  </H3>';
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
        $testOrSubmit = false;
        /// TEST ///
        //$timeslot_id_array [] = 1;
        
        //////////////// RECORD/TEST ///////////////////////////////////////////////////

        if(! $submitted_calendar)
        {
            if(array_key_exists("Test",$_POST))
            {
                testData($dbh,$error_list,$timeslot_id_array);
                $testOrSubmit = true;
            }
            else if(array_key_exists("Envoyer",$_POST) )
            {
                $testOrSubmit = true;
                $test_result = testData($dbh,$error_list,$timeslot_id_array);
                if($test_result)
                {
                    //TODO repeter x fois si prblm de deadlock
                    
                    $submitted_calendar = recordData($dbh,$error_list,$timeslot_id_array); //on essaye d'enregistrer ou de tester les donnees
                
                    //on recharge les slots user
                    if($submitted_calendar)
                    {
                        $user_slot = getUserSlot($dbh);//on recupere les slots que l'utilisateur a reserve
                    }
                }
            }
        }

        //////////////// PRINT /////////////////////////////////////////////////////////

        if($submitted_calendar) //l'utilisateur a deja enregistre son agenda
        {        
            printUserSlot($user_slot); //on affiche son planning
            printBonus($dbh,$user_slot, true);    //on affiche ses bonus
            printSlotDescription();     
        }
        else //l'utilisateur n'a pas encore enregistre son agenda
        {
            $available_slot = getAllAvailableSlot($dbh); //on recupere les slots horaire encore disponible
            $available_slot = checkCondition($dbh,$error_list,$available_slot, "pre"); //pre-filtering                 
            list($sorted_data,$selected_db_items) = sortData($error_list,$available_slot, $timeslot_id_array); //on trie les donn&eacute;es par jour et par event
            //findMissingSlot($dbh,$error_list, $timeslot_id_array, $available_slot); //identification des plages qui aurait disparue depuis le pr&eacute;c&eacute;dent test
            
            if(count($error_list) == 0)//si erreur, on affiche pas les bonus
            {
                printBonus($dbh,$selected_db_items, $testOrSubmit); //affichage des bonus, s'il y en a
            }
            
            printError($error_list); //on affiche les erreurs, s'il y en a
            echo "<BR />";
            printForm($sorted_data, $timeslot_id_array); //affichage du formulaire permettant de choisir ses slots
            printBonusTable($dbh); //on affiche comment obtenir les bonus
            printSlotDescription();
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

$dbh = null;
?>
</body></html>




