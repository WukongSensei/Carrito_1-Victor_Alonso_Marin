<?php
header('Content-Type: application/json');

// Rutas de los archivos XML
$carritoFile = 'Carrito.xml';
$stockFile = 'Stock.xml';

// Función para cargar el XML
function loadXml($file) {
    if (file_exists($file)) {
        return simplexml_load_file($file);
    }
    return null;
}

// Función para guardar el XML
function saveXml($file, $xml) {
    $xml->asXML($file);
}

// Si se recibe una solicitud POST para agregar un producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Agregar producto al carrito
    if ($action === 'add') {
        $nombre = $_POST['nombre'];
        $cantidad = $_POST['cantidad'];

        // Cargar el stock
        $stockXml = loadXml($stockFile);
        $productoStock = null;

        // Buscar el producto en el stock
        foreach ($stockXml->producto as $producto) {
            if ((string)$producto->nombre === $nombre) {
                $productoStock = $producto;
                break;
            }
        }

        // Verificar si hay suficiente stock
        if ($productoStock && (int)$productoStock->cantidad >= (int)$cantidad) {
            // Cargar el carrito existente
            $carritoXml = loadXml($carritoFile);

            // Crear un nuevo producto en el carrito
            $productoCarrito = $carritoXml->addChild('producto');
            $productoCarrito->addChild('productID', uniqid());
            $productoCarrito->addChild('nombre', $nombre);
            $productoCarrito->addChild('cantidad', $cantidad);
            $productoCarrito->addChild('precio_unidad', (float)$productoStock->precio_unidad);

            // Actualizar stock
            $productoStock->cantidad = (int)$productoStock->cantidad - (int)$cantidad;

            // Guardar los archivos XML
            saveXml($carritoFile, $carritoXml);
            saveXml($stockFile, $stockXml);

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No hay suficiente stock disponible.']);
        }
        exit;
    }

    // Cargar productos del carrito
    if ($action === 'load_cart') {
        $carritoXml = loadXml($carritoFile);
        $productos = [];

        foreach ($carritoXml->producto as $producto) {
            $productos[] = [
                'productID' => (string)$producto->productID,
                'nombre' => (string)$producto->nombre,
                'cantidad' => (int)$producto->cantidad,
                'precio_unidad' => (float)$producto->precio_unidad,
            ];
        }

        echo json_encode($productos);
        exit;
    }

    // Cargar stock
    if ($action === 'load_stock') {
        $stockXml = loadXml($stockFile);
        $productos = [];

        foreach ($stockXml->producto as $producto) {
            $productos[] = [
                'nombre' => (string)$producto->nombre,
                'precio_unidad' => (float)$producto->precio_unidad,
                'cantidad' => (int)$producto->cantidad,
            ];
        }

        echo json_encode($productos);
        exit;
    }

    // Limpiar el carrito
    if ($action === 'clear_cart') {
        $carritoXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><productos></productos>');
        saveXml($carritoFile, $carritoXml);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Eliminar un producto del carrito
    if ($action === 'remove') {
        $productID = $_POST['productID'];
        
        // Cargar el carrito
        $carritoXml = loadXml($carritoFile);
        $productoEliminado = false;

        // Buscar y eliminar el producto
        foreach ($carritoXml->producto as $producto) {
            if ((string)$producto->productID === $productID) {
                $cantidadEliminada = (int)$producto->cantidad;
                $nombreProducto = (string)$producto->nombre;

                // Eliminar el producto del carrito
                $dom = dom_import_simplexml($producto);
                $dom->parentNode->removeChild($dom);

                $productoEliminado = true;

                // Actualizar stock
                $stockXml = loadXml($stockFile);
                foreach ($stockXml->producto as $productoStock) {
                    if ((string)$productoStock->nombre === $nombreProducto) {
                        $productoStock->cantidad = (int)$productoStock->cantidad + $cantidadEliminada;
                        break;
                    }
                }
                // Guardar el stock actualizado
                saveXml($stockFile, $stockXml);
                break;
            }
        }

        // Guardar el carrito actualizado
        saveXml($carritoFile, $carritoXml);

        echo json_encode(['status' => $productoEliminado ? 'success' : 'error', 
            'message' => $productoEliminado ? 'Producto eliminado exitosamente.' : 'Producto no encontrado.']);
        exit;
    }
}
?>
