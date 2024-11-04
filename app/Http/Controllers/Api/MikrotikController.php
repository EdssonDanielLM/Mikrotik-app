<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MikrotikOs;
use App\MyHelper\RouterosAPI;

class MikrotikController extends Controller
{
    public $API=[], $mikrotikos_data=[], $connection;
    
    public function test_api()
    {
        try{
            return response()->json([
                'success' => true,
                'message' => 'Bienvenido Mikrotik API'
            ]);
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotik API'
            ]);
        }
    }

    public function store_mikrotikos($data)
    {
        $API = new RouterosAPI;
        $connection = $API->connect($data['ip_address'], $data['login'], $data['password']);

        if(!$connection) return response()->json(['error' => true, 'message' => 'Routeros no conectado ...'], 404);

        $store_mikrotikos_data = [
            'identity' => $API->comm('/system/identity/print')[0]['name'],
            'ip_address' => $data['ip_address'],
            'login' => $data['login'],
            'password' => $data['password'],
            'connect' => $connection  

        ];

        $store_mikrotikos = new MikrotikOs;
        $store_mikrotikos ->identity = $store_mikrotikos_data['identity'];
        $store_mikrotikos ->ip_address = $store_mikrotikos_data['ip_address'];
        $store_mikrotikos ->login = $store_mikrotikos_data['login'];
        $store_mikrotikos ->password =$store_mikrotikos_data['password'];
        $store_mikrotikos ->connect =$store_mikrotikos_data['connect'];
        $store_mikrotikos->save();

        return response()->json([
            'success' => true,
            'message' => 'Los datos de Routeros se han guardado en la base de datos dbmikrotik',
            'mikrotikos_data' => $store_mikrotikos
        ]);
    }

    public function mikrotikos_connection(Request $request)
    {
        try{

            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'login' => 'required',
                'password' => 'required'
            ]);
            
            if($validator->fails()) return response()->json($validator->errors(), 404);

            $req_data = [
                'ip_address' => $request->ip_address,
                'login' => $request->login,
                'password' => $request->password
            ];

            $mikrotikos_db = MikrotikOs::where('ip_address', $req_data['ip_address'])->get();

            if(count($mikrotikos_db) > 0){
                if($this->check_mikrotik_connection($request->all())):
                    return response()->json([
                        'connect' => true,
                        'message' => 'Los Routeros tienen una conexión desde la base de datos',
                        'mikrotikos_data' => $this->mikrotikos_data
                    ]);
                else:
                    return response()->json([
                        'error' => true,
                        'message' => '¡Routeros no conectados comprobar login administrador !'
                    ]);
                endif;

            }else{
                return $this->store_mikrotikos($request->all());
            }

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotik API, '.$e->getMessage()
            ]);
        }
    }

    public function check_mikrotik_connection($data){

        $mikrotikos_db = MikrotikOs::where('ip_address', $data[
            'ip_address'])->get();

        if(count($mikrotikos_db) > 0):
            //echo $mikrotikos_db[0]['identity']; die;
            $API = new RouterosAPI;
            $connection = $API->connect($mikrotikos_db[0]['ip_address'],
            $mikrotikos_db[0]['login'], $mikrotikos_db[0]['password']);
            if(!$connection) return false;
            
            $this->API = $API;
            $this->connection = $connection;
            $this->mikrotikos_data = [
                'identity' => $this->API->comm('/system/identity/print')[0]['name'],
                'ip_address' => $mikrotikos_db[0]['ip_address'],
                'login' => $mikrotikos_db[0]['login'],
                'connect' => $connection
            ];
            return true;
        else:
            echo "Los datos de Routeros no son válidos en la base de datos, por favor cree la conexión de nuevo!";
        endif;
    }

    public function set_interface(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'id' => 'required',
                'interface' => 'required',
                'name' => 'required'
            ]);

            if($validator->fails()) return response()->json($validator->errors(), 404);
            
            if($this->check_mikrotik_connection($request->all())):
                $interface_lists = $this->API->comm('/interface/print');
                $find_interface = array_search($request->name, array_column($interface_lists, 'name'));
                
                if(!$find_interface):
                    $set_interface = $this->API->comm('/interface/set', [
                        '.id' => "*$request->id",
                        'name' => "$request->name"
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Se ha establecido correctamente la interfaz de : $request->interface, to : $request->name",
                        'interface_lists' => $this->API->comm('/interface/print')
                    ]);
                else:
                    return response()->json([
                        'success' => false,
                        'message' => "Nombre de la interfaz : $request->interface, with .id : *$request->id ya ha sido tomada de routeros",
                        'interface_lists' => $this->API->comm('/interface/print')
                    ]);
            endif;

        endif;
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotik API, '.$e->getMessage()
            ]);
        }
    }

    public function add_new_address(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'address' => 'required',
                'interface' => 'required'
            ]);

            if($validator->fails()) return response()->json($validator->errors(), 404);

            if($this->check_mikrotik_connection($request->all())):
                $add_address = $this->API->comm('/ip/address/add', [
                    'address' => $request->address,
                    'interface' =>$request->interface
                ]);

                $list_address = $this->API->comm('/ip/address/print');

                $find_address_id = array_search($add_address, array_column($list_address, '.id'));

                if(!$find_address_id) return response()->json([
                    'success' => false,
                    'message' => $add_address['!trap'][0]['message'],
                    'address_lists' => $list_address,
                    'mikrotikos_data' =>$this->mikrotikos_data
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Añadida con éxito la nueva dirección de la interfaz : $request->interface",
                    'address_lists' => $list_address
                ]);

            endif;

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotik API, '.$e->getMessage()
            ]);
        }
    }

    public function add_ip_route(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'gateway' => 'required'
            ]);
            if ($validator->fails()) return response()->json($validator->errors(), 404);

            if($this->check_mikrotik_connection($request->all())):
                $route_lists = $this->API->comm('/ip/route/print');

                $find_route_lists = array_search($request->gateway, array_column($route_lists, 'gateway'));

                if($find_route_lists === 0):
                    return response()->json([
                        'success' =>false,
                        'message' => "Gateway address : $request->gateway ya se ha tomado",
                        'route_lists' => $this->API->comm('/ip/route/print')
                    ]);

                else:
                    $add_route_lists = $this->API->comm('/ip/route/add', [
                        'gateway' => $request->gateway
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => "Añadida con éxito nuevo route gateway : $request->gateway",
                        'route_lists' => $this->API->comm('/ip/route/print'),
                        'mikrotikos_data' => $this->mikrotikos_data
                    ]);
                endif;

            endif;
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetc data Routeros API, '.$e->getMessage()
            ]);
        }
    }

    public function add_dns_servers(Request $request)
    {
        try{
            $schema = [
                'ip_address' => 'required',
                'servers' => 'required',
                'remote_requests' => 'required'
            ];
            $validator = Validator::make($request->all(), $schema);

            if($validator->fails()) return response()->json($validator->errors(), 404);

            if($this->check_mikrotik_connection($request->all())):
                $add_dns = $this->API->comm('/ip/dns/set', [
                    'servers' => $request->servers,
                    'allow-remote-requests' => $request->remote_requests
                ]);

                $dns_lists = $this->API->comm('/ip/dns/print');

                if(count($add_dns) == 0):
                    return response()->json([
                        'success' => true,
                        'message' => 'Añadidos con éxito nuevos servidores dns',
                        'dns_lists' => $dns_lists
                    ]);
                else:
                    return response()->json([
                        'success' => false,
                        'message' => 'Fallo al añadir servidores dns',
                        'mikrotikos_data' => $this->mikrotikos_data
                    ]);
                endif;
            endif;

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotikos API, '.$e->getMessage()
            ]);
        }
    }

    public function routeros_reboot(Request $request)
    {
        try{
            $schema = [
                'ip_address' => 'required'
            ];

            $validator = Validator::make($request->all(), $schema);

            if($validator->fails()) return response()->json($validator->errors(), 404);

            if($this->check_mikrotik_connection($request->all())):
                $reboot = $this->API->comm('/system/reboot');

                return response()->json([
                    'reboot' => true,
                    'message' => 'Routeros has been reboot the system',
                    'connection' => $this->connection
                ]);

            endif;

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error fetch data Mikrotik API, '.$e->getMessage()
            ]);
        }
    }

    public function add_user(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'username' => 'required',
                'password' => 'required',
                'profile' => 'required'
            ]);
    
            if ($validator->fails()) return response()->json($validator->errors(), 404);
    
            if ($this->check_mikrotik_connection($request->all())):
                // Obtener la lista de usuarios existentes
                $existing_users = $this->API->comm('/ip/hotspot/user/print');
    
                // Comprobar si el usuario ya existe
                $user_exists = array_search($request->username, array_column($existing_users, 'name'));
    
                if ($user_exists !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => "El usuario con nombre {$request->username} ya existe en el servidor.",
                        'user_data' => $existing_users[$user_exists]
                    ]);
                }
    
                // Si el usuario no existe, proceder a añadirlo
                $new_user = $this->API->comm('/ip/hotspot/user/add', [
                    'name' => $request->username,
                    'password' => $request->password,
                    'profile' => $request->profile
                ]);
    
                return response()->json([
                    'success' => true,
                    'message' => "Usuario {$request->username} agregado con éxito.",
                    'user_data' => $new_user
                ]);
            endif;
    
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al intentar agregar el usuario en MikroTik, ' . $e->getMessage()
            ]);
        }
    }
    
    public function set_bandwidth_limit(Request $request)
    {
        try {
            // Validación de datos
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'target' => 'required', // IP o nombre de usuario
                'download_limit' => 'required', // Ej: '2M' para 2 Mbps
                'upload_limit' => 'required'    // Ej: '1M' para 1 Mbps
            ]);
    
            if ($validator->fails()) return response()->json($validator->errors(), 404);
    
            // Verificar conexión a MikroTik
            if ($this->check_mikrotik_connection($request->all())) {
                // Comprobar que la API está inicializada
                if (is_object($this->API)) {
                    // Configurar límite de ancho de banda
                    $set_queue = $this->API->comm('/queue/simple/add', [
                        'name' => $request->target,
                        'target' => $request->target,
                        'max-limit' => "{$request->upload_limit}/{$request->download_limit}"
                    ]);
    
                    return response()->json([
                        'success' => true,
                        'message' => "Límite de ancho de banda establecido para {$request->target}.",
                        'queue_data' => $set_queue
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'La API no está inicializada correctamente.'
                    ]);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al establecer límite de ancho de banda: ' . $e->getMessage()
            ]);
        }
    }

    public function create_user_group(Request $request)
    {
        try {
            // Validación de datos de entrada
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required',
                'group_name' => 'required',
                'policies' => 'required'  // Ej: "ssh,ftp,winbox,read"
            ]);
    
            if ($validator->fails()) return response()->json($validator->errors(), 404);
    
            // Verificar conexión a MikroTik
            if ($this->check_mikrotik_connection($request->all())) {
                // Comprobar que la API está inicializada
                if (is_object($this->API)) {
                    // Verificar si el grupo ya existe
                    $existingGroup = $this->API->comm('/user/group/print', [
                        '?name' => $request->group_name
                    ]);
    
                    if (!empty($existingGroup)) {
                        return response()->json([
                            'success' => false,
                            'message' => "El grupo de usuario '{$request->group_name}' ya existe.",
                            'group_data' => $existingGroup
                        ]);
                    }
    
                    // Crear grupo de usuario
                    $create_group = $this->API->comm('/user/group/add', [
                        'name' => $request->group_name,
                        'policy' => $request->policies
                    ]);
    
                    // Obtener lista actualizada de grupos
                    $group_lists = $this->API->comm('/user/group/print');
    
                    return response()->json([
                        'success' => true,
                        'message' => "Grupo de usuario '{$request->group_name}' creado con éxito.",
                        'group_lists' => $group_lists
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'La API no está inicializada correctamente.'
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo conectar a RouterOS. Verifique los datos de conexión.'
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo en RouterOS: ' . $e->getMessage()
            ]);
        }
    }    

}