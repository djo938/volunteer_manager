<?php
session_start();

$dbh = new PDO('mysql:host=localhost;dbname=marsinne', "root", "root");

//SI y a des post d'auth?

/*if(isset($_POST['username']) && isset($_POST['password']))
{
    unset($_SESSION['user_id']);
    
    //on tente une auth
    $stmt = $dbh->prepare("SELECT * from Users where username = :uname");
    $stmt->bindParam(':uname', $_POST['username']);
	$stmt->execute();
    
	if( ($var = $stmt->fetch()) &&  $_POST['password'] == $var["password"]) //TODO is a validated user ?
	{
	    $_SESSION['user_id'] = $var["ID_Users"];
	}
	else
	{
	    $authError = "Echec de connexion";
	}
}*/

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
    //TODO SI Planning de l'user rempli
        //TODO on affiche un tableau statique de son horaire

    //TODO SINON
    if(isset($_POST["Test"]) || isset($_POST["Envoyer"])) //TODO SI donnees de post
    {
        if(isset($_POST["Test"]))
        {$test = true;}
        else
        {$test = false;}
        
        //TODO recuperer la liste des cases cochees
        
        //TODO verifier le nombre de slot
        
        //TODO verifier que les slots ne s'overlape pas
        
        //TODO calculer les bonus
        
        if(! $test)
        {
            //TODO on ajoute dans la DB
                //transaction
                
                
            //TODO si pas d'erreur, ne pas afficher le planning
        }
    }
        
        
        //SINON
            //on affiche le tableau avec les checkbox
    
    //recuperation des Timeslot
    $stmt = $dbh->prepare("SELECT * from Timeslot"); //TODO uniquement recuperer les Timeslot ou il y a de la place
    $stmt->execute();
    $a = $stmt->fetchAll();
            
    //data pre parsing
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
            $tmp = DateTime::createFromFormat("Y-m-d H:i:s",$value["Start_time"]);
    		$tmp->setTime(3,0,0);
    		$tmp->add(new DateInterval("P1D"));

            //ajout de l'ensemble du jour dans la liste des jours
    		$sorted_data[$tmp->format("l d F")] = &$current_day;  //TODO ne pas utiliser la limite mais la date du premier event de la journee
    		
    		//application de la nouvelle limite
    		$next_limit = $tmp->format("Y-m-d H:i:s");
    	}
        
        //l'event existe deja aujourd'hui ?
    	if( !array_key_exists($value["Description"],$current_day))
    	{
    		$current_day[$value["Description"]] = array();
    	}

        //ajout de l'event
    	$current_day[$value["Description"]][] = $value;
    }
    
    //TODO afficher les erreurs
        //TODO calculer si des plages ont disparues depuis les erreurs ou le test
    

    echo "<form METHOD=\"POST\" ACTION=\"test.php\">";

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
    		    //TODO cocher les cases en provenances des erreurs ou du test
    		    
    			$first_line  .= "<td><INPUT type=\"checkbox\" name=\"".$occurrence["ID_Timeslot"]."\">".DateTime::createFromFormat("Y-m-d H:i:s",$occurrence["Start_time"])->format("H:i")."-".DateTime::createFromFormat("Y-m-d H:i:s",$occurrence["End_time"])->format("H:i")."</td>";
    		    $second_line .= "<td>".$occurrence["NumberOfPeople"]." place(s)</td>";
    		}
    		$first_line  .= "</tr>";
    		$second_line .= "</tr>";
    		
    		echo $first_line.$second_line."</table></td></tr>";
    	}
    	echo "</table>";
    }
    ?>
    <BR/><INPUT type="submit" value="Test">
    <INPUT type="submit" value="Envoyer">

    </form>
    <?php
}
?>





