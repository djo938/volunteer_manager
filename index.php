<html><head></head><body>
	<?php
    
    function startswith($hay, $needle) {
      return substr($hay, 0, strlen($needle)) === $needle;
    }
    
    include 'config.inc.php';
    
	//var_dump($_POST);echo "<BR />";
	
	if(isset($_POST['nom']) && isset($_POST['prenom']) && isset($_POST['mail']) && isset($_POST['gsm']) 
	   && isset($_POST['username']) && isset($_POST['password1']) && isset($_POST['password2']))
	{
	    $error_array = array();
	    
		if(isset($_POST['secour']))
		{$secour = true;}
		else
		{$secour = false;}

		//aucun champs ne doit etre vide
		if(strlen($_POST['nom']) == 0)
		{
			$error_array['nom'] = "Le nom ne peut pas &#234;tre vide";
		}

		if(strlen($_POST['prenom']) == 0)
		{
			$error_array['prenom'] = "Le prenom ne peut pas &#234;tre vide";
		}

		if(strlen($_POST['mail']) == 0)
		{
			$error_array['mail'] = "Le mail ne peut pas &#234;tre vide";
		}

		if(strlen($_POST['gsm']) == 0)
		{
			$error_array['gsm'] = "Le gsm ne peut pas &#234;tre vide";
		}

		if(strlen($_POST['username']) == 0)
		{
			$error_array['username'] = "Le nom d'utilisateur ne peut pas &#234;tre vide";
		}

		if(strlen($_POST['password1']) == 0)
		{
			$error_array['password1'] = "Le mot de passe ne peut pas &#234;tre vide";
		}

        //validitee adresse mail
	    if(! isset($error_array['mail']) && ! filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL))
		{
		    $error_array['mail'] = "Adresse email invalide";
		}

		//verifier le mot de passe
		if(! isset($error_array['password1']) && $_POST['password1'] != $_POST['password2'])
		{
		    $error_array['password1'] = "La r&eacute;p&eacute;tition du mot de passe n'est pas valide";
		}
		
		//check validite GSM
		if(!isset($error_array['gsm']))
		{
		    $clean_gsm = preg_replace("/\D+/","", $_POST['gsm']);
            //echo $clean_gsm."<BR />";

            $expected_length = 0;
            if(startswith($clean_gsm,"0033") || startswith($clean_gsm,"0032")) //0032 491 23 45 67
            {
                $expected_length = 13;
            }
            else if(startswith($clean_gsm,"33") || startswith($clean_gsm,"32"))//32 491 23 45 67
            {
                $expected_length = 11;
            }
            else//0 491 23 45 67
            {
                $expected_length = 10;
            }
            
            if(strlen($clean_gsm) != $expected_length)
            {
                $error_array['gsm'] = "format gsm invalide (valeurs accept&eacute;es: 0491 23 45 67, +32491 23 45 67, 0032 491 23 45 67)";
            }
		}
		

		//TODO ajouter et verifier le capcha

        //check the DB and insert
        $dbh = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);
        if(!isset($error_array['username']))
        {
            //verifier l'existance du username
    		
    		$stmt = $dbh->prepare("SELECT * from Users where username = :uname");
    		$stmt->bindParam(':uname', $_POST['username']);
    		$stmt->execute();

    		if($stmt->fetch())
    		{
    			//il y a deja un username avec le nom
                $error_array['username'] = "l'utilisateur existe d&eacute;j&agrave;";
    		}
        }
        
        if(count($error_array) == 0)
        {
            //TODO manage the error (try/catch)
            
            //si pas d'erreur, ajouter l'utilisateur
            $stmt = $dbh->prepare("INSERT INTO Users(Name, Family_name, Mail, GSM, backup, username, password, user_type) VALUES(:name, :fname, :mail, :gsm, :backup, :uname, :pwd, 'new')");
            $stmt->bindParam(':name', $_POST['nom']);
            $stmt->bindParam(':fname', $_POST['prenom']);
            $stmt->bindParam(':mail', $_POST['mail']);
            $stmt->bindParam(':gsm', $_POST['gsm']);
            $stmt->bindParam(':backup', $secour);
            $stmt->bindParam(':uname', $_POST['username']);
            $md5_hash = md5($_POST['password1']);
            $stmt->bindParam(':pwd', $md5_hash); //TODO hassh the password
            if(!$stmt->execute())
            {
                echo "<H1>Echec de l'ajout de l'utilisateur.  Si l'erreur persiste, merci de bien vouloir contacter l'administrateur &agrave; l'adresse suivante : webmaster@folkfestivalmarsinne.be</H1>";
            }
            else
            {
                echo "<H1>Votre candidature a bien &eacute;t&eacute; enregistr&eacute;e, vous allez &ecirc;tre contact&eacute; par mail pour la suite</H1>";
                //TODO envoyer un mail
                
            }
            echo "L'&eacute;quipe technique du site web folkfestivalmarsinne.be    ";
            
        }
        else
        {
            //sinon afficher a nouveau le formulaire avec les erreurs
            ?>
            <form method="POST" action="./index.php">
            	    <table BORDER=0>
            	    	<tr><td>Nom : </td>                        <td><INPUT type="text" name="nom" value="<?php echo $_POST['nom']; ?>"></td></tr>
            	    	<tr><td>Prenom : </td>                     <td><INPUT type="text" name="prenom" value="<?php echo $_POST['prenom']; ?>"></td></tr>
            	    	<tr><td>Adresse mail : </td>               <td><INPUT type="text" name="mail" value="<?php echo $_POST['mail']; ?>"></td></tr>
            	    	<tr><td>GSM : </td>                        <td><INPUT type="text" name="gsm" value="<?php echo $_POST['gsm']; ?>"></td></tr>
            	    	<tr><td>B&eacute;n&eacute;vole de secour : </td> <td><INPUT type="checkbox" name="secour" <?php if($secour){echo "checked";} ?>></td></tr>
            	    	<tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username" value="<?php echo $_POST['username']; ?>"></td></tr>
            	    	<tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password1"></td></tr>
            	    	<tr><td>Mot de passe (2) : </td>               <td><INPUT type="password" name="password2"></td></tr>
            	    	<tr><td></td><td></td></tr>
            	    	<tr><td></td>                              <td><br /><INPUT type="submit" value="Inscription"></td></tr>
            	    </table>
            	</form>
            <?php
            foreach ($error_array as $i => $value) 
        	{
        	    echo $value."<BR />";
            }
        }
	}
	else
	{
	?>
	<form method="POST" action="./index.php">
	    <table BORDER=0>
	    	<tr><td>Nom : </td>                        <td><INPUT type="text" name="nom"></td></tr>
	    	<tr><td>Prenom : </td>                     <td><INPUT type="text" name="prenom"></td></tr>
	    	<tr><td>Adresse mail : </td>               <td><INPUT type="text" name="mail"></td></tr>
	    	<tr><td>GSM : </td>                        <td><INPUT type="text" name="gsm"></td></tr>
	    	<tr><td>B&eacute;n&eacute;vole de secour : </td> <td><INPUT type="checkbox" name="secour"></td></tr>
	    	<tr><td>Nom d'utilisateur : </td>          <td><INPUT type="text" name="username"></td></tr>
	    	<tr><td>Mot de passe : </td>               <td><INPUT type="password" name="password1"></td></tr>
	    	<tr><td>Mot de passe (2) : </td>               <td><INPUT type="password" name="password2"></td></tr>
	    	<tr><td></td><td></td></tr>
	    	<tr><td></td>                              <td><br /><INPUT type="submit" value="Inscription"></td></tr>
	    </table>
	</form>
	<?php } 
	
	
	
	?>

</body></html>