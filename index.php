<?php session_start(); ?>
<html><head></head><body>
	<?php
    
    include 'config.inc.php';
    
    function printForm($data = array())
    {
        ?>
            <form method="POST" action="./index.php">
            	    <table BORDER=0>
            	    	<tr><td>Nom (*) : </td>                                         <td><INPUT type="text" name="nom" <?php if(array_key_exists('nom',$data)){echo "value=\"".$_POST['nom']."\"";} ?>></td></tr>
            	    	<tr><td>Prenom (*) : </td>                                      <td><INPUT type="text" name="prenom" <?php if(array_key_exists('prenom',$data)){echo "value=\"".$_POST['prenom']."\"";} ?>></td></tr>
            	    	<tr><td>Adresse postale (*) : </td>                             <td><INPUT type="text" name="postal" <?php if(array_key_exists('postal',$data)){echo "value=\"".$_POST['postal']."\"";} ?>></td></tr>
            	    	<tr><td>Adresse email (*) : </td>                               <td><INPUT type="text" name="mail" <?php if(array_key_exists('mail',$data)){echo "value=\"".$_POST['mail']."\"";} ?>></td></tr>
            	    	<tr><td>GSM (*) : </td>                                         <td><INPUT type="text" name="gsm" <?php if(array_key_exists('gsm',$data)){echo "value=\"".$_POST['gsm']."\"";} ?>></td></tr>
            	    	<tr><td>T&eacute;l&eacute;phone fixe : </td>                    <td><INPUT type="text" name="fix" <?php if(array_key_exists('fix',$data)){echo "value=\"".$_POST['fix']."\"";} ?>></td></tr>
            	    	<tr><td>B&eacute;n&eacute;vole de secours (1) : </td>               <td><INPUT type="checkbox" name="secour" <?php if(array_key_exists('secour',$data)){echo "checked";} ?>></td></tr>
            	    	<tr><td>Nom d'utilisateur (*) : </td>                           <td><INPUT type="text" name="username" <?php if(array_key_exists('username',$data)){echo "value=\"".$_POST['username']."\"";} ?>></td></tr>
            	    	<tr><td>Brevet de secourisme (ou &eacute;quivalent) : </td>     <td><INPUT type="text" name="mede" <?php if(array_key_exists('mede',$data)){echo "value=\"".$_POST['mede']."\"";} ?>></td></tr>
            	    	<tr><td>Mot de passe (*) : </td>                                <td><INPUT type="password" name="password1"></td></tr>
            	    	<tr><td>Mot de passe (bis) (*) : </td>                            <td><INPUT type="password" name="password2"></td></tr>
            	    	<tr><td>Permis et voiture : </td>                               <td>non<INPUT type=radio name="car" value="non" <?php if(!array_key_exists('car',$data) || ($data['car'] != "licence" && $data['car'] != "car")){echo "checked";} ?>>, juste le permis B<INPUT type=radio name="car" value="licence" <?php if(array_key_exists('car',$data) && $data['car'] == "licence"){echo "checked";} ?>>, voiture et permis B<INPUT type=radio name="car" value="car" <?php if(array_key_exists('car',$data) && $data['car'] == "car"){echo "checked";} ?>></td></tr>
            	    	<tr><td>Conditions d'utilisation : </td>                        <td><INPUT type="checkbox" name="condu" <?php if(array_key_exists('condu',$data)){echo "checked";} ?>> J'ai lu et j'accepte les conditons d'utilisation d&eacute;crites ci-dessous</td></tr>
            	    	<tr><td></td><td></td></tr>
            	    	<tr><td COLSPAN="2"><img id="captcha" src="./securimage/securimage_show.php" alt="CAPTCHA Image" /></td></tr>
            	    	<tr><td COLSPAN="2">	<input type="text" name="captcha_code" size="10" maxlength="6" /> <a href="#" onclick="document.getElementById('captcha').src = './securimage/securimage_show.php?' + Math.random(); return false">[ Different Image ]</a></td></tr>
            	    	<tr><td></td><td></td></tr>
            	    	<tr><td COLSPAN="2">(*) = champ obligatoire</td></tr>
            	    	<tr><td></td>                              <td><br /><INPUT type="submit" value="Inscription"></td></tr>
            	    </table>
            	</form>
            	(1) : Il peut arriver que certains b&eacute;n&eacute;voles ne se pr&eacute;sentent pas &agrave; leur poste &agrave; temps, il faut donc que certains b&eacute;n&eacute;voles soient accessibles au pied lev&eacute;. Si vous faites partie de l'&eacute;quipe des b&eacute;n&eacute;voles de secours, je pourrai vous contacter par gsm ou verbalement pour vous proposer de remplacer le b&eacute;n&eacute;vole absent. Si vous acceptez, vous recevrez des tickets boissons comme si vous aviez prest&eacute; cette tranche horaire. Notez que vous pouvez refuser de remplacer le b&eacute;n&eacute;vole absent.
    	<?php
    }
    
    function printCond()
    {
        ?>
        	<h3>Conditions d'utilisation</h3>
        	Les donn&eacute;es personnelles recueillies dans ce formulaire seront collect&eacute;es et trait&eacute;es conform&eacute;ment &agrave; la r&eacute;glementation en vigueur, en particulier la loi du 8 d&eacute;cembre 1992 sur la protection de la vie priv&eacute;e.<BR />
            Ces donn&eacute;es personnelles ne sont trait&eacute;es que dans la mesure strictement n&eacute;cessaire &agrave; la gestion des activit&eacute;s b&eacute;n&eacute;voles dans le cadre du Folk Festival Marsinne (constitution d'une r&eacute;serve de candidats invit&eacute;s &agrave; participer en qualit&eacute; de volontaires au Folk Festival Marsinne. Identification/authentification des b&eacute;n&eacute;voles ; r&eacute;partition des t&acirc;ches ; attributions des postes ; communication des horaires et instructions de travail).<BR />
            Ces donn&eacute;es sont trait&eacute;es sous la responsabilit&eacute; du responsable des b&eacute;n&eacute;voles et du responsable informatique du Folk Festival Marsinne. Elles ne seront pas communiqu&eacute;es &agrave; des tiers ni utilis&eacute;es &agrave; des fins commerciales. <BR />
            A tout moment, vous conservez le droit d'acc&eacute;der &agrave; vos donn&eacute;es personnelles et de les rectifier si n&eacute;cessaire. Pour ce faire, vous pouvez envoyer un email &agrave; <a href="mailto:webmaster@folkfestivalmarsinne.be">webmaster@folkfestivalmarsinne.be</a>. De m&ecirc;me, vous demeurez &agrave; tout moment libre de supprimer vos donn&eacute;es personnelles, toujours en envoyant un mail &agrave; <a href="mailto:webmaster@folkfestivalmarsinne.be">webmaster@folkfestivalmarsinne.be</a>. <BR />
            En compl&eacute;tant votre fiche d'inscription, vous vous d&eacute;clarez d'accord avec le traitement de vos donn&eacute;es personnelles dans les conditions d&eacute;crites ci-dessus. <BR />
        <?php
    }
    
    function startswith($hay, $needle) {
      return substr($hay, 0, strlen($needle)) === $needle;
    }
    	
	if(   array_key_exists('nom', $_POST) 
	   && array_key_exists('prenom',$_POST) 
	   && array_key_exists('postal',$_POST) 
	   && array_key_exists('mail',$_POST) 
	   && array_key_exists('gsm',$_POST) 
	   && array_key_exists('username',$_POST) 
	   && array_key_exists('password1',$_POST) 
	   && array_key_exists('password2', $_POST))
	{
	    $error_array = array();
	    
	    include_once './securimage/securimage.php';

        $securimage = new Securimage();
        
        if ($securimage->check($_POST['captcha_code']) == false)
        {
            $error_array['captcha'] = "Le captcha est incorrect";
        }
	    
	    if(!isset($_POST['condu']))
	    {
	        $error_array['condu'] = "Pour s'inscrire, vous devez accepter les conditions d'utilisation";
	    }
	    
		if(isset($_POST['secour']))
		{$secour = true;}
		else
		{$secour = false;}

        if(array_key_exists('fix',$_POST) )
        {$fix = $_POST["fix"];}
        else
        {$fix = "";}
        
        if(array_key_exists('mede',$_POST) )
        {$mede = $_POST["mede"];}
        else
        {$mede = "";}

		//aucun champs ne doit etre vide
		if(strlen($_POST['nom']) == 0)       {$error_array['nom'] = "Le nom ne peut pas &#234;tre vide";}
		if(strlen($_POST['prenom']) == 0)    {$error_array['prenom'] = "Le prenom ne peut pas &#234;tre vide";}
        if(strlen($_POST['postal']) == 0)    {$error_array['postal'] = "L'adresse postale ne peut pas &#234;tre vide";}
		if(strlen($_POST['mail']) == 0)      {$error_array['mail'] = "Le mail ne peut pas &#234;tre vide";}
		if(strlen($_POST['gsm']) == 0)       {$error_array['gsm'] = "Le gsm ne peut pas &#234;tre vide";}
		if(strlen($_POST['username']) == 0)  {$error_array['username'] = "Le nom d'utilisateur ne peut pas &#234;tre vide";}
		if(strlen($_POST['password1']) == 0) {$error_array['password1'] = "Le mot de passe ne peut pas &#234;tre vide";}

        //validitee adresse mail
	    if(! filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL))
		{
		    $error_array['mail'] = "Adresse email invalide";
		}

		//verifier le mot de passe
		if( $_POST['password1'] != $_POST['password2'])
		{
		    $error_array['password1'] = "La r&eacute;p&eacute;tition du mot de passe n'est pas valide";
		}
		
		//check validite GSM
		if(!isset($error_array['gsm']))
		{
		    //retire tout sauf les chiffres
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
		
		//check fix
		if(strlen($fix) > 0)
		{
		    //9 et commence par 0
		    //10 et commence par 32
		    //12 et commence par 0032
		    $clean_fix = preg_replace("/\D+/","", $fix);
		    
		    $expected_length = 0;
            if(startswith($clean_fix,"0032")) //0032 491 23 45 67
            {
                $expected_length = 12;
            }
            else if(startswith($clean_fix,"32"))//32 491 23 45 67
            {
                $expected_length = 10;
            }
            else//0 491 23 45 67
            {
                $expected_length = 9;
            }
            
            if(strlen($clean_fix) != $expected_length)
            {
                $error_array['fix'] = "format t&eacute;l&eacute;phone fixe invalide (valeurs accept&eacute;es: 012 34 56 78, +3212 34 56 78, 0032 12 34 56 78)";
            }
		}

        //check the DB and insert
        $dbh = new PDO('mysql:host='.$DATABASE_SERVER.';dbname='.$DATABASE_NAME.'', $DATABASE_USERNAME, $DATABASE_PASSWORD);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try
        {
            //check username
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
        
            //check mail
            if(!isset($error_array['mail']))
            {
                //verifier l'existance du username
    		
        		$stmt = $dbh->prepare("SELECT * from Users where Mail = :mail");
        		$stmt->bindParam(':mail', $_POST['mail']);
        		$stmt->execute();

        		if($stmt->fetch())
        		{
        			//il y a deja un username avec le nom
                    $error_array['mail'] = "l'adresse mail existe d&eacute;j&agrave;";
        		}
            }
        
            if(count($error_array) == 0)
            {            
                //si pas d'erreur, ajouter l'utilisateur
                $stmt = $dbh->prepare("INSERT INTO Users(Name,  Family_name, Mail,  GSM,  backup,  username, password, user_type, register_date, postal, fix,  medecine) 
                                                  VALUES(:name, :fname,      :mail, :gsm, :backup, :uname,   :pwd,     'new',     NOW(),        :postal, :fix, :mede)");
                $stmt->bindParam(':name', $_POST['nom'], PDO::PARAM_STR, 300);
                $stmt->bindParam(':fname', $_POST['prenom'], PDO::PARAM_STR, 300);
                $stmt->bindParam(':mail', $_POST['mail'], PDO::PARAM_STR, 300);
                $stmt->bindParam(':gsm', $_POST['gsm'], PDO::PARAM_STR, 30);
                $stmt->bindParam(':backup', $secour);
                $stmt->bindParam(':uname', $_POST['username'], PDO::PARAM_STR, 300);
                $md5_hash = md5($_POST['password1']);
                $stmt->bindParam(':pwd', $md5_hash, PDO::PARAM_STR, 300);
                $stmt->bindParam(':postal', $_POST['postal'], PDO::PARAM_STR, 300);
                $stmt->bindParam(':fix', $fix, PDO::PARAM_STR, 30);
                $stmt->bindParam(':mede', $mede, PDO::PARAM_STR, 300);
            
            
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
                printForm($_POST);
                echo "<h3>Erreur(s)</h3><ul>";
                foreach ($error_array as $i => $value) 
            	{
            	    echo "<li />".$value;
                }
                echo "</ul>";
                printCond();
                
            }
            $dbh = null;
        }
	    catch(PDOException $err)
        {
            if($DEBUG)
            {
                echo "Erreur: ".$err;
            }

            echo "<BR/>Il semblerait qu'un probleme avec la base de donn&eacute;es ait eu lieu.  Si le probl&egrave;me persiste, contactez l'administrateur du site: webmaster@folkfestivalmarsinne.be";
        }
	}
	else
	{
	    printForm();
	    printCond();
	} 
	?>

</body></html>
