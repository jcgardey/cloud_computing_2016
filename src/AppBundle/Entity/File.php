<?php
	
	namespace AppBundle\Entity;

	class File {

		private $name;

		private $email;


		public function setName($aName) {
			$this->name = $aName;
		}

		public function getName() {
			return $this->name;
		}

		public function setEmail($anEmail) {
			$this->email = $anEmail;
		}

		public function getEmail() {
			return $this->email;
		}
	}