<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\File;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\Template;

use Excel;

class UsersController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $req)
	{
		if ( auth()->user()->role_id == 1 ){
			$roles_id = [];
			if ( Route::currentRouteName() == 'User.index1' ){//System users
				$roles_id = [1,2,3];
			} else {//App users
				$roles_id = [4,5];
			}

			$users = User::whereIn('role_id',$roles_id)->get();
		} else {
			$users = User::whereIn('branch_id', auth()->user()->branches->pluck('id'))->get();
		}

		$roles = Role::all()->pluck('name','id')->prepend('Todos los roles', 0)->except(['id', '4']);
		$templates = Template::where('status',1)->get();

		if ($req->ajax()) {
			return view('users.table',compact('users'))->render();
		}
		return view('users.index',compact('users', 'roles', 'templates'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function form($type, $id = null){

		$roles_ids = array();
		if ( $type == 'sistema' ) { 
			if ( auth()->user()->role_id == 1 ) { $roles_ids = [4,5]; }//Admin
			elseif ( auth()->user()->role_id == 2 ) { $roles_ids = [1,2,4,5]; }//Branch allowed only to create receptionist users
		}
		elseif ( $type == 'app' ) { 
			if ( auth()->user()->role_id == 1 ) { $roles_ids = [1,2,3]; }//Admin
			elseif ( auth()->user()->role_id == 2 ) { $roles_ids = [1,2,3,4,5]; } //Branch is not allowed to create app users
		}

		if ( $id ){
			$user = User::findOrFail($id);
		} else {
			$user = new User();
		}

		$roles = Role::whereNotIn('id',$roles_ids)->orderBy('id', 'desc')->pluck('name','id');
		return view('users.form', compact('user', 'roles', 'type'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(UserRequest $req)
	{
		$user = new User();
		$user->fill($req->all());
		$user->password = bcrypt($req->password);

		if ($user->save()){
			$params = array();
			$params['subject'] = "Nuevo usuario creado";
			$params['title'] = "Accesos a la plataforma";
			$params['content']['message'] = "Has sido dado de alta como usuario de sistema de ".env('APP_NAME').", estos son tus accesos para tu cuenta:";
			$params['content']['email'] = $user->email;
			$params['content']['password'] = $req->password;
			$params['email'] = $user->email;
			$params['view'] = 'mails.credentials';

			if ( $this->mail( $params ) ){
				return redirect()->route($user->role->env ==  'App' ? 'User.index2' : 'User.index1')->with(['msg' => 'Usuario creado', 'class' => 'alert-success']);
			}
			return redirect()->route($user->role->env ==  'App' ? 'User.index2' : 'User.index1')->with([ 'msg' => 'Usuario creado, ocurrió un problema al enviar el correo', 'class' => 'alert-warning' ]);
		} else {
			return back()->with([ 'msg' => 'Error al crear el usuario', 'class' => 'alert-danger' ]);
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(UserRequest $req, $id)
	{
		$user = User::find($id);
		$user->fill($req->except('password', 'email'));
		$pass = $req->password;
		if ( $pass ) {
			$user->password = bcrypt($pass);
		}

		if ( $user->save() ){
			if ( $pass ){
				$params = array();
				$params['subject'] = "Usuario de sistema modificado";
				$params['content']['message'] = "Tu contraseña ha sido modificada, este es tu nuevo acceso:";
				$params['content']['email'] = $user->email;
				$params['content']['password'] = $req->password;
				$params['title'] = "Accesos al sistema";
				$params['email'] = $user->email;
				$params['view'] = 'mails.credentials';

				if ( /*$this->mail*/($params) ){
					return redirect()->route($user->role->env ==  'App' ? 'User.index2' : 'User.index1')->with(['msg' => 'Usuario actualizado', 'class' => 'alert-success']);
				}
				return redirect()->route($user->role->env ==  'App' ? 'User.index2' : 'User.index1')->with([ 'msg' => 'Usuario actualizado, ocurrió un problema al enviar el correo', 'class' => 'alert-warning' ]);
			}
			return redirect()->route($user->role->env ==  'App' ? 'User.index2' : 'User.index1')->with(['msg' => 'Usuario actualizado', 'class' => 'alert-success']);
		} else {
			return back()->with([ 'msg' => 'Error al actualizar usuario', 'class' => 'alert-danger' ]);
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id)
	{
		$user = User::find($id);
		if ( User::destroy($id) ) {
			return ['delete' => 'true'];
		} else {
			return ['delete' => 'false'];
		}
	}

	public function status(Request $req)
	{
		$user = User::find($req->id);
		$user->status = $user->status?0:1;

		if ( $user->save() ) {
			return ['status' => true];
		} else {
			return ['status' => false];
		}
	}

	/**
	 * Función para cambiar la foto de perfil
	 *
	 * @return Arreglo con el usuario modificado
	 */
	public function updatePhotoProfile(Request $req, $id){
		$user = User::find($id);

		if ( $req->hasFile('photo') ){
			if ($req->base64) {
				$encoded_data = $req->base64;
				$binary_data = base64_decode( $encoded_data );

				File::makeDirectory(public_path()."/img/profiles/".$user->id, 0777, true, true);

				if ( File::exists(public_path()."/img/profiles/".$user->id) ){
					File::cleanDirectory(public_path()."/img/profiles/".$user->id."/");
				}
				$filename = time().'.jpg';

				$user->photo = '/img/profiles/'.$user->id.'/'.$filename;
				$ruta_foto = 'img/profiles/'.$user->id.'/'.$filename;

				$result = file_put_contents( $ruta_foto, $binary_data );
			}
		}

		if ( $user->save() ){
			return redirect()->back();
		}
		return redirect()->back();
	}

	/**
     * Use Excel instance to import many customers at once.
     *
     * @return \Illuminate\Http\Response
     */
    public function import_customers(Request $req)
    {
    	set_time_limit(0);
        $file = $req->excel_file;
        if( $file ) {
            $path = $file->getRealPath();
            $extension = $file->getClientOriginalExtension();
            if ( $extension == 'xlsx' || $extension == 'xls' ) {
                $data = Excel::load($path, function($reader) {
                    $reader->setDateFormat('Y-m-d');
                })->get();

                if ( !empty( $data ) && $data->count() ) {
                    foreach ( $data as $key => $value ) {

                        $insert = [
                            'fullname' => mb_strtoupper( $value->fullname ),
                            'email' => mb_strtolower( $value->email ),
                            'password' => bcrypt( $value->password ),
                            'phone' => $value->phone,
                            'regime' => ( strtolower( $value->regime ) == "persona física" ? "Persona física" : ( strtolower( $value->regime ) == "persona moral" ? "Persona moral" : "Persona física" ) ),
                            'rfc' => strtoupper( $value->rfc ),
                            'role_id' => 4, #customer
                        ];

                        User::updateOrCreate([
                            'fullname' => $insert['fullname'],
                            'email' => $insert['email'],
                            'rfc' => $insert['rfc']
                        ], $insert);
                    }
                }//End data count if
                return response(['msg' => 'Registros validados correctamente', 'status' => 'success'], 200);
            }//End of extension if
        } else {
            return response(['msg' => 'No hay archivo para verificar', 'status' => 'error'], 404);
        }
    }

    /**
     * Use Excel instance to export all app users at once, even the deleted one.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $req)
    {
    	$rows = array();
        
		$roles_id = [4];

		$users = User::whereIn('role_id',$roles_id)->withTrashed()->get();

        foreach ( $users as $users ) {
            $rows [] = 
            [
                'Nombre completo' => $users->fullname,
                'Email' => $users->email,
                'Teléfono' => $users->phone,
                'RFC' => $users->rfc,
                'Dirección' => $users->address,
                'Acividad empresarial' => $users->business_activity,
                'Tipo de identificación' => $users->identification_type,
                'Número de identificación' => $users->identification_num,
                'Status de usuario' => $users->deleted_at ? 'Eliminado' : 'Activo',
            ];
        }

        Excel::create('Usuarios', function( $excel ) use ( $rows ) {
            $excel->sheet('Hoja 1', function( $sheet ) use ( $rows ) {
                $sheet->cells('A:I', function( $cells ) {
                    $cells->setAlignment('center');
                    $cells->setValignment('center');
                });
                
                $sheet->cells('A1:I1', function( $cells ) {
                    $cells->setFontWeight('bold');
                });

                $sheet->fromArray( $rows );
            });
        })->export('xlsx');
    }

    /**
     * Send a specific template to selected prospects
     *
     * @return \Illuminate\Http\Response
     */
    public function send_template(Request $req)
    {
        $template = Template::find($req->template_id);
        $users = User::whereIn('id', $req->users_ids)->get();
        $emails = [];

        $users->each(function($user, $key) use (&$emails){
            $emails[] = $user->email;
        });

        $params = array();
        $params['subject'] = "Información Fast Office";
        $params['title'] = $template->name;
        $params['content']['message'] = $template->content;
        $params['content']['attachments'] = $template->attachments;
        $params['email'] = $emails;
        $params['view'] = 'mails.templates';

        if ( $this->mail($params) ){
            return response(['msg' => 'Plantilla enviada', 'status' => 'success'], 200);
        }
        return response(['msg' => 'Error al enviar la plantilla', 'status' => 'error'], 404);
    }
}
