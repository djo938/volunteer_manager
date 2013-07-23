<?php

//////////////// UTILS FUNCTION ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function tryToAuth($dbh, $user_type, $cookie_name)
{   
    unset($_SESSION[$cookie_name]);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname and user_type = :utype and password = :password");        
    $stmt->bindParam(':uname', $_POST['username']);
    $stmt->bindParam(':utype', $user_type);
    $md5_mdp = md5($_POST['password']);
    $stmt->bindParam(':password', $md5_mdp);
    $stmt->execute();
    
    if( ($var = $stmt->fetch()))
    {
        $_SESSION[$cookie_name] = $var["ID_Users"];
    }
}

function printAuthForm($target)
{
    ?>
    <form method="POST" action="<?php echo $target; ?>">
            <table BORDER=0>
                <tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username"></td></tr>
                <tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password"></td></tr>
                <tr><td></td><td></td></tr>
                <tr><td></td>                              <td><br /><INPUT type="submit" name="connect" value="Se connecter"></td></tr>
            </table>
        </form>
    <?php
}

function buildLimitFromDBDatetime($value)
{
    $new_next_limit = fromMySQLDatetimeToPHPDatetime($value);

    if($new_next_limit->format("H:i") > "03:00")
    {
        $new_next_limit->add(new DateInterval("P1D")); //si on est avant minuit, on ajoute un jour de + a la limite
    }
    
    $new_next_limit->setTime(3,0,0); //on definit la limite de la journee a 3h00 du matin
    
    //application de la nouvelle limite
    return $new_next_limit->format("Y-m-d H:i:s");
}

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

function compute_bonus($dbh, $slot_list)
{
    //echo 'BONUS <BR />';
    
    //var_dump($slot_list);
    
    //$value_to_print = array();
    if(count($slot_list) > 0)
    {
        $bonus_to_load = array();
    
        $first = true;
        $req_part = "";
        //$i = 1;
        foreach($slot_list as $index=>$value)
        {
            //si la plage n'est pas disponible, on ne la comptabilise plus
            /*if($value["remaining"] == 0)
            {
                continue;
            } XXX $slot_list est dejq epure*/
            
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
    
        $SQL = "SELECT Bonus_items.Description, sum(Bonus_items_bonus.bonus_count) as sum
                FROM Bonus_items_bonus, Bonus_items
                WHERE Bonus_items_bonus.ID_Bonus_item = Bonus_items.ID_Bonus_item
                AND Bonus_items_bonus.ID_Bonus in 
                (SELECT T.id FROM ( SELECT ID_Bonus as id, max(Bonus.threshold) as max
                	FROM Bonus 
                	WHERE ".$req_part."	
                	GROUP BY Bonus.cat) T)
                GROUP BY Bonus_items.ID_Bonus_item";
    
        /*$SQL = "SELECT description, max(threshold) 
                FROM Bonus 
                WHERE ".$req_part." 
                GROUP BY cat";*/
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
    
        /*foreach($a as $index=>$value)
        {
            $value_to_print[] = $value["sum"]." fois : ".$value["Description"];
        }*/
        
        return $a;
    }
    return array();
}

function fromMySQLDatetimeToPHPDatetime($mysql_datetime)
{
    return DateTime::createFromFormat("Y-m-d H:i:s",$mysql_datetime);
}

function getSlotDescriptionFromDBSlot($value)
{
    $datetime_start = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
    $datetime_end = fromMySQLDatetimeToPHPDatetime($value["End_time"]);
    
    return "\"".$value["Description"]."\" ".$datetime_start->format("H:i")."-".$datetime_end->format("H:i");
}

function getSlotStartDateFromDBSlot($value)
{
    $datetime_start = fromMySQLDatetimeToPHPDatetime($value["Start_time"]);
    
    return $datetime_start->format("l d F");
}

?>