<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use AppBundle\Entity\File;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class DefaultController extends Controller
{
    
    private function createClient () {
        $client = new \Google_Client();
        $client->setAuthConfig('client_secrets.json');
        $client->addScope('https://www.googleapis.com/auth/drive');
        $client->setRedirectUri($this->generateUrl('autenticate',array(),UrlGeneratorInterface::ABSOLUTE_URL));
        return $client;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        
        $session = $request->getSession();
        $accessToken = $session->get('google_token');
        $client = $this->createClient();
      
        if ($accessToken) {
            
            $client->setAccessToken($accessToken);

            $drive_service = new \Google_Service_Drive($client);
            $files_list = $drive_service->files->listFiles(array());

            return $this->render('default/index.html.twig',array('files' => $files_list->getFiles()));

        }
        else {
            $auth_url = $client->createAuthUrl();
        	return new RedirectResponse($auth_url); 
        }
    }

    
    /**
     * @Route("/autenticate", name="autenticate")
     */
    public function autenticateAction (Request $request) {
        $client = $this->createClient();
        if ($request->get('code')) {
            $accessToken = $client->fetchAccessTokenWithAuthCode($request->get('code'));
            $request->getSession()->set('google_token', $accessToken);
            $request->getSession()->set('refresh_token', $client->getRefreshToken());
            
            return $this->redirectToRoute('homepage');
        }
        if ($request->get('error')) {
            return $this->render('default/error.html.twig',array());   
        }
         
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function uploadFileAction(Request $request) {
    	$client = $this->createClient();
    	$session = $request->getSession();
    	$accessToken = $session->get('google_token');
    	if ($accessToken) {
    		$client->setAccessToken($accessToken);

    		$file = new File ();
	    	$form = $this->createFormBuilder($file)
	    		->add('name', TextType::class)
	    		->add('email', EmailType::class)
	    		->add('enviar', SubmitType::class)
	    		->getForm();

	    	$form->handleRequest($request);
	    	if ($form->isSubmitted() && $form->isValid()) {
	    		$drive_service = new \Google_Service_Drive($client);
	    		
	    		$driveFile = new \Google_Service_Drive_DriveFile();
				$driveFile->setName($file->getName().'.doc');
				$driveFile->setMimeType('application/vnd.google-apps.document');
				$result = $drive_service->files->create($driveFile, array('mimeType' => 'application/vnd.google-apps.document'));

				$fileCreated = $drive_service->files->get($result->getId(), array ('fields' => 'webViewLink'));
				var_dump($fileCreated);

				$fileCreated->setId($result->getId());

				$permission = new \Google_Service_Drive_Permission();
			    $permission->setRole('writer');
			    $permission->setType('user');
			    $permission->setEmailAddress($file->getEmail());
			    $drive_service->permissions->create($fileCreated->getId(), $permission);

			    return $this->render('default/upload.html.twig', array('file' => $fileCreated));
	    	}
	    	return $this->render('default/upload.html.twig',array('form' => $form->createView()));
    	}
    	else {
    		$auth_url = $client->createAuthUrl();
        	return new RedirectResponse($auth_url); 
    	}

    }


    /**
     * @Route("/show_file/{id_file}", name = "show_file")
     */
    public function showAction(Request $request, $id_file) {
    	$client = $this->createClient();
    	$session = $request->getSession();
    	$accessToken = $session->get('google_token');
    	if ($accessToken) {
    		$client->setAccessToken($accessToken);
    		$drive_service = new \Google_Service_Drive($client);
    		$file = $drive_service->files->get($id_file, array ('fields' => 'name,webViewLink'));
    		$file->setId($id_file);

    		$defaultDataShareForm = array ();
    		$shareForm = $this->createFormBuilder($defaultDataShareForm)
    			->add('email', EmailType::class)
    			->add('enviar', SubmitType::class)
    			->getForm();

    		$defaultDataUnShareForm = array ();
    		$unShareForm = $this->createFormBuilder($defaultDataUnShareForm)
    			->add('email', EmailType::class)
    			->add('enviar', SubmitType::class)
    			->getForm();

    		$shareForm->handleRequest($request);
    		if ($shareForm->isSubmitted() && $shareForm->isValid() ) {
    			$share_data = $shareForm->getData();

    			$permission = new \Google_Service_Drive_Permission();
			    $permission->setRole('writer');
			    $permission->setType('user');
			    $permission->setEmailAddress($share_data["email"]);
			    $drive_service->permissions->create($file->getId(), $permission);
    		}

    		$unShareForm->handleRequest($request);
    		if ($unShareForm->isSubmitted() && $unShareForm->isValid() ) {
    			$unshare_data = $unShareForm->getData();
    			
    		}
    		
    		return $this->render('default/show.html.twig', array('file' => $file, 'shareForm' => $shareForm->createView() ));
    	}
    	else {
    		$auth_url = $client->createAuthUrl();
        	return new RedirectResponse($auth_url); 
    	}

    }

    
    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction (Request $request) {
    	$session = $request->getSession();
    	
    	$client = $this->createClient();
    	$client->revokeToken($session->get('google_token'));
       
        $session->remove('google_token');
        $session->invalidate();

        return $this->redirectToRoute('homepage');

    }

}
