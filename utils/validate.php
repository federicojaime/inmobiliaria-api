<?php
	namespace utils;

	class Validate {
		private $conn = null;
		private $errors = [];

		public function __construct($db = null) {
			$this->conn = $db;
		}

		public function validar($fields, $verificaciones) {
			$valores = (object) $fields;
			$this->errors = [];
			/*
				"lastname" => [
					"type" => "string"
				]

				$key => [
					$rule => $value
				]

				$v = $fields->{$key} el valor que se desea verificar contra las reglas
			*/
			foreach($verificaciones as $key => $rules) {
				//if(!isset($valores->{$key})) {
				if(!property_exists($valores, $key)) {
					$this->errors[] = "{$key} es un valor requerido.";
				} else {
					$v = $valores->{$key};
					$last_type = null;
					foreach($rules as $rule => $value) {
						//echo("{$key}: regla: {$rule} - ${value} <br/> \n");
						switch($rule) {
							case "type":
								$last_type = $value;
								switch($value) {
									case "number":
										if(!is_numeric($v)) {
											$this->errors[] = "{$key} [$v] no es un número válido.";
										}
										break;
									case "string":
										if(!is_string($v)) {
											$this->errors[] = "{$key} [$v] no es una cadena de caracteres válida.";
										}
										break;
									case "date":
										if(strtotime($v) == false) {
											$this->errors[] = "{$key} [$v] no es una fecha válida.";
										}
										break;
									case "time":
										$formato = 'H:i:s';
										$horario_obj = DateTime::createFromFormat($formato, $v);
										if(!($horario_obj && $horario_obj->format($formato) === $v)) {
											$this->errors[] = "{$key} [$v] no es un horario válido.";
										}
										break;
									case "array":
										if(!is_array($v)) {
											$this->errors[] = "{$key} no es un array válido.";
										}
										break;
									default:
										$this->errors[] = "{$value} no es un 'type' válido.";
										break;
								}
								break;
							case "min":
								switch($last_type) {
									case "number":
										if($v < $value) {
											$this->errors[] = "{$key} [$v] no debe ser menor a {$value}.";
										}
										break;
									case "string":
										if(strlen($v) < $value) {
											$this->errors[] = "{$key} [$v] debe tener al menos {$value} caracteres.";
										}
										break;
									case "date":
										$fecha1_obj = new DateTime($v);
										$fecha2_obj = new DateTime($value);
										if($fecha1_obj->diff($fecha2_obj)->format('%R') === '-') {
											$this->errors[] = "{$key} [$v] debe ser menor a la fecha {$value}.";
										}
										break;
									case "time":
										$formato = 'H:i:s';
										$horario_obj = DateTime::createFromFormat($formato, $v);
										if(!($horario_obj && $horario_obj->format($formato) === $v && $horario_obj >= DateTime::createFromFormat($formato, $value))) {
											$this->errors[] = "{$key} [$v] no debe ser menor a la hora {$value}.";
										}
										break;
									case "array":
										if(empty($v) || (count($v) < $value)) {
											$this->errors[] = "{$key} debe tener al menos {$value} item/s.";
										}
										break;
									default:
										break;
								}
								break;
							case "max":
								switch($last_type) {
									case "number":
										if($v > $value) {
											$this->errors[] = "{$key} [$v] no debe ser mayor a {$value}.";
										}
										break;
									case "string":
										if(strlen($v) > $value) {
											$this->errors[] = "{$key} [$v] debe tener como máximo {$value} caracteres.";
										}
										break;
									case "date":
										$fecha1_obj = new DateTime($v);
										$fecha2_obj = new DateTime($value);
										if($fecha1_obj->diff($fecha2_obj)->format('%R') === '+') {
											$this->errors[] = "{$key} [$v] no debe ser mayor que la fecha {$value}.";
										}
										break;
									case "time":
										$formato = 'H:i:s';
										$horario_obj = DateTime::createFromFormat($formato, $v);
										if(!($horario_obj && $horario_obj->format($formato) === $v && $horario_obj <= DateTime::createFromFormat($formato, $value))) {
											$this->errors[] = "{$key} [$v] no debe ser mayor a la hora {$value}.";
										}
										break;
									case "array":
										if(count($v) > $value) {
											$this->errors[] = "{$key} debe tener como máximo {$value} item/s.";
										}
										break;
									default:
										break;
								}
								break;
							case "isValidMail":
								if($last_type === "string" && (!filter_var($v, FILTER_VALIDATE_EMAIL))) {
									$this->errors[] = "{$key} [$v] no es una dirección eletrónica válida.";
								}
								break;
							case "unique":
								if(!is_null($this->conn)) {
									try {
										$query = "SELECT id FROM {$value} WHERE {$key} = :{$key}";
										$stmt = $this->conn->prepare($query);
										$stmt->bindParam(":" . $key, $v);
										if($stmt->execute()) {
											$count = $stmt->rowCount();
											if($count > 0) {
												$this->errors[] = "El valor [$v] ya existe en la tabla {$value} -> {$key}.";
											}
										}
									} catch (\PDOException $e) {
										$this->errors[] = "{$rule}: " . $e->getMessage();
										$this->errors[] = "{$rule}: " . $query;
									} catch (Exception $e) {
										$this->errors[] = "{$rule}: " . $e->getMessage();
									}
								} else {
									$this->errors[] = "No se ha especificado para la regla {$rule} una conexión a DB válida.";
								}
								break;
							case "exist":
								if(!is_null($this->conn)) {
									try {
										$query = "SELECT id FROM {$value} WHERE {$key} = :{$key}";
										$stmt = $this->conn->prepare($query);
										$stmt->bindParam(":" . $key, $v);
										if($stmt->execute()) {
											$count = $stmt->rowCount();
											if($count == 0) {
												$this->errors[] = "El valor [$v] no existe en la tabla {$value} -> {$key}.";
											}
										}
									} catch (\PDOException $e) {
										$this->errors[] = "{$rule}: " . $e->getMessage();
										$this->errors[] = "{$rule}: " . $query;
									} catch (Exception $e) {
										$this->errors[] = "{$rule}: " . $e->getMessage();
									}
								} else {
									$this->errors[] = "No se ha especificado para la regla {$rule} una conexión a DB válida.";
								}
								break;
							default:
								$this->errors[] = "{$rule} no es una regla válida.";
								break;
						}
					}
				}
			}
		}

		public function hasErrors() {
			if(count($this->errors) > 0) {
				return true;
			}
			return false;
		}

		public function getErrors() {
			$resp = new \stdClass();
			$resp->ok = false;
			$resp->msg = "Hay errores en los datos suministrados";
			$resp->data = null;
			$resp->errores = $this->errors;
			return $resp;
		}
	}
?>