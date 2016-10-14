<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class DefaultController extends Controller
{
    
    /**
     * @Route("/", name="index")
     */
    public function indexAction () {
    	return $this->render('default/index.html.twig', array());
    }


    private function createClient () {
        $client = new \Google_Client();
        $client->setAuthConfig('client_secrets.json');
        $client->addScope('https://www.googleapis.com/auth/drive');
        $client->setRedirectUri($this->generateUrl('autenticate',array(),UrlGeneratorInterface::ABSOLUTE_URL));
        return $client;
    }

    /**
     * @Route("/home", name="homepage")
     */
    public function homeAction(Request $request)
    {
        
        $session = $request->getSession();
        $accessToken = $session->get('google_token');
        $client = $this->createClient();
      
        if ($accessToken) {
            
            $client->setAccessToken($accessToken);

            $drive_service = new \Google_Service_Drive($client);
            $files_list = $drive_service->files->listFiles(array());

            return $this->render('default/home.html.twig',array('files' => $files_list->getFiles()));

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

             $logger = $this->get('logger');
    		$logger->info('ENTRO EN AUTENTICATE');
            
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

    		$default_data = array('name' => 'file');
	    	$form = $this->createFormBuilder($default_data)
	    		->add('name', TextType::class, array (
	    			'constraints' => array (
	    				new NotBlank()
	    			)
	    		))
	    		->add('enviar', SubmitType::class)
	    		->getForm();

	    	$form->handleRequest($request);
	    	if ($form->isSubmitted() && $form->isValid()) {
	    		$form_data = $form->getData();

	    		$drive_service = new \Google_Service_Drive($client);
	    
	    		$driveFile = new \Google_Service_Drive_DriveFile();
				$driveFile->setName($form_data["name"].'.doc');
				$driveFile->setMimeType('application/vnd.google-apps.document');
				$result = $drive_service->files->create($driveFile, array('mimeType' => 'application/vnd.google-apps.document'));

				return $this->redirectToRoute('homepage');				

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

    		$shareForm = $this->createShareForm();
    		$unShareForm = $this->createUnShareForm();

    		$shareForm->handleRequest($request);
    		if ($shareForm->isSubmitted() && $shareForm->isValid() ) {
    			$share_data = $shareForm->getData();

				$this->savePermission($share_data["share_email"], $id_file);    			
			    return $this->redirectToRoute('homepage');
    		}

    		$unShareForm->handleRequest($request);
    		if ($unShareForm->isSubmitted() && $unShareForm->isValid() ) {
    			$unshare_data = $unShareForm->getData();
    			$this->deletePermission($unshare_data["unshare_email"], $id_file);
    			return $this->redirectToRoute('homepage');

    		}    		
    		return $this->render('default/show.html.twig', array('file' => $file, 'shareForm' => $shareForm->createView(), 
    			'unshareForm' => $unShareForm->createView() ));
    	}
    	else {
    		$auth_url = $client->createAuthUrl();
        	return new RedirectResponse($auth_url); 
    	}

    }

    private function savePermission($anEmail, $file_id) {
    	$permission = new \Google_Service_Drive_Permission();
		$permission->setRole('writer');
		$permission->setType('user');
		$permission->setEmailAddress($anEmail);
		$drive_service->permissions->create($file->getId(), $permission);
    }

    private function deletePermission($anEmail, $file_id) {
    	$permissions = $drive_service->permissions->listPermissions($id_file)->getPermissions();
    	foreach ($permissions as $permission) {
    		if ($permission->getEmailAddress() == $anEmail) {
    			$drive_service->permissions->delete($file_id, $permission);
    		}
    	}
    }

    private function createShareForm() {
    	$defaultDataShareForm = array ();
    	$shareForm = $this->createFormBuilder($defaultDataShareForm)
    			->add('share_email', EmailType::class, array (
    				'constraints' => array (
    					new Email()
    				)
    			))
    			->add('enviar', SubmitType::class)
    			->getForm();

    	return $shareForm;
    }

    private function createUnShareForm () {
    	$defaultDataUnShareForm = array ();
    		$unShareForm = $this->createFormBuilder($defaultDataUnShareForm)
    			->add('unshare_email', EmailType::class, array (
    				'constraints' => array (new Email())
    			))
    			->add('enviar', SubmitType::class)
    			->getForm();

    	return $unShareForm;
    }

    
    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction (Request $request) {
    	$session = $request->getSession();
    	
    	$client = $this->createClient();
    	$client->revokeToken($session->get('google_token')); 
       
        $session->invalidate(1); 

        return $this->redirectToRoute('index');

    }

}
