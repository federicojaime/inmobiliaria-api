<?php
// services/ImageService.php
namespace services;

class ImageService {
    // Definimos la carpeta de subida usando una ruta absoluta basada en __DIR__
    private $upload_dir;
    private $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    private $max_size = 5242880; // 5MB

    public function __construct() {
        // La carpeta "uploads/properties" se ubicará en la raíz de tu proyecto (por ejemplo, al mismo nivel que index.php)
        $this->upload_dir = __DIR__ . '/../uploads/properties/';
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }

    public function uploadImage($file) {
        try {
            $this->validateImage($file);
            
            // Obtén la extensión a partir del nombre original
            $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            // Si por alguna razón la extensión viene vacía, usa "jpg" por defecto
            if (empty($extension)) {
                $extension = 'jpg';
            }
            $filename = bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $this->upload_dir . $filename;
            
            // Mueve el archivo a la carpeta de uploads
            $file->moveTo($filepath);
            
            // Retornamos la ruta relativa para poder acceder a la imagen vía navegador
            return [
                'success' => true,
                'path' => 'uploads/properties/' . $filename
            ];
        } catch (\Exception $e) {
            // Puedes descomentar la siguiente línea para debug (se escribe en el log de errores de PHP)
            // error_log("ImageService error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function validateImage($file) {
        // Verifica que no haya error en la subida
        if ($file->getError() !== UPLOAD_ERR_OK) {
            // Para debug, registra el código de error
            error_log("Error en la subida: " . $file->getError());
            throw new \Exception('Error en la subida del archivo');
        }
        // Valida el tipo de contenido
        if (!in_array($file->getClientMediaType(), $this->allowed_types)) {
            throw new \Exception('Tipo de archivo no permitido');
        }
        // Valida el tamaño del archivo
        if ($file->getSize() > $this->max_size) {
            throw new \Exception('El archivo excede el tamaño máximo permitido');
        }
    }

    public function deleteImage($filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
            return true;
        }
        return false;
    }
}
