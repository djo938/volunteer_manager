<?php

    function formatFrench($format) 
    {
        $english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        $french_days = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
        $english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'Décember');
        $french_months = array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');
        return str_replace($english_months, $french_months, str_replace($english_days, $french_days, $format));
    }

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
    if(count($slot_list) > 0)
    {
        //TODO remove this hack
        $montage_demontage_count = 0;
        $weekend_slot = 0;
        foreach($slot_list as $index=>$slot)
        {
            if($slot["Description"] == "Montage" || $slot["Description"] == "D&eacute;montage")
            {
                $montage_demontage_count += 1;
                continue;
            }
            
            if($slot["Start_time"] > "2013-09-06 03:00:00" && $slot["Start_time"] <= "2013-09-09 03:00:00")
            {
                $weekend_slot += 1;
            }
        }
        
        //if 5 plages of montage/demontage OR if 6 plages from friday to sunday
        if($montage_demontage_count >= 5 || $weekend_slot >= 6)
        {
            //entrée 3 jours, 5 repas, 15 boissons offertes + Café et eau gratuits
            $to_return = array();
            $to_return [] = array("Description" => "entr&eacute;e d'un jour au choix",0 => "entr&eacute;e d'un jour au choix", "sum" => "3", 1 => "3");
            //$to_return [] = array("Description" => "caf&eacute; + eau gratuits le vendredi",0 => "caf&eacute; + eau gratuits le vendredi", "sum" => "1", 1 => "1");
            //$to_return [] = array("Description" => "entr&eacute;e samedi ou dimanche au choix",0 => "entr&eacute;e samedi ou dimanche au choix", "sum" => "2", 1 => "2");
            $to_return [] = array("Description" => "caf&eacute; + eau gratuits le jour de la place gratuite",0 => "caf&eacute; + eau gratuits le jour de la place gratuite", "sum" => "3", 1 => "3");
            $to_return [] = array("Description" => "un repas",0 => "un repas", "sum" => "5", 1 => "5");
            $to_return [] = array("Description" => "une boisson",0 => "une boisson", "sum" => "15", 1 => "15");
            return $to_return;
        }
        //XXX end of the hack  

        $bonus_to_load = array();
    
        $first = true;
        $req_part = "";
        
        foreach($slot_list as $index=>$value)
        {
            if(array_key_exists($value["Bonus_cat"],$bonus_to_load))
            {
                $bonus_to_load[intval($value["Bonus_cat"])] += 1;
            }
            else
            {
                $bonus_to_load[intval($value["Bonus_cat"])] = 1;
            }
        }
    
        foreach($bonus_to_load as $cat=>$count)
        {
            if($first)
            {
                $req_part = "Bonus_items_bonus.ID_Bonus = (SELECT ID_Bonus 
                                    				  	   FROM Bonus B
                                    					   WHERE B.cat = ".$cat." AND B.threshold = (SELECT max(Bonus.threshold)
                                    												   	      FROM Bonus 
                                    													      WHERE cat = B.cat AND threshold <= ".$count." 
                                    													      GROUP BY Bonus.cat))";
                $first = false;
            }
            else
            {
                $req_part .= " OR Bonus_items_bonus.ID_Bonus = (SELECT ID_Bonus 
                                    				  	   FROM Bonus B
                                    					   WHERE B.cat = ".$cat." AND B.threshold = (SELECT max(Bonus.threshold)
                                    												   	      FROM Bonus 
                                    													      WHERE cat = B.cat AND threshold <= ".$count." 
                                    													      GROUP BY Bonus.cat))";
            }
        }
    
        $SQL = "SELECT Bonus_items.Description, SUM(Bonus_items_bonus.bonus_count) AS sum
                FROM Bonus_items_bonus, Bonus_items
                WHERE Bonus_items_bonus.ID_Bonus_item = Bonus_items.ID_Bonus_item
                AND (".$req_part.")
                GROUP BY Bonus_items.ID_Bonus_item";
    
        $stmt = $dbh->prepare($SQL);    
        $stmt->execute();
        $a = $stmt->fetchAll();
        
        //var_dump($a);
              
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