<?php
namespace Database\Seeders;
use App\Models\{Adviser,Helper,HelpSeeker,Moderator,User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
class AdviserDemoUsersSeeder extends Seeder {
    public const PASSWORD = 'CompassDemo!2026';
    public static function accounts(): array {
        $rows=[];
        foreach(['adviser','helper','moderator','seeker'] as $role) foreach([1,2] as $number) {
            $alias='DemoSeeker0'.$number;
            $email=$role==='seeker' ? strtolower($alias).'@compass.local' : 'demo.'.$role.$number.'@compass.local';
            $rows[]=['role'=>$role,'number'=>$number,'email'=>$email,'login'=>$role==='seeker' ? $alias : $email];
        }
        return $rows;
    }
    public function run(): void {
        if(!app()->environment(['local','testing'])) throw new \RuntimeException('These demonstration accounts are local/test only.');
        DB::transaction(function() {
            $advisers=[];
            foreach(self::accounts() as $account) {
                $role=$account['role']; $number=$account['number'];
                $name=$role==='seeker' ? $account['login'] : 'Demo '.ucfirst($role).' '.$number;
                $user=User::where('email',$account['email'])->first();
                if($user && ($user->role!==$role || $user->name!==$name || !Hash::check(self::PASSWORD,$user->password))) throw new \RuntimeException('An existing account conflicts with the requested demo account; it was not overwritten.');
                $user ??= User::create(['email'=>$account['email'],'name'=>$name,'password'=>self::PASSWORD,'role'=>$role,'is_active'=>true,'email_verified_at'=>now()]);
                if($role==='seeker') {
                    HelpSeeker::firstOrCreate(['user_account_id'=>$user->id],['generated_alias'=>$account['login'],'pseudo_id'=>'DEMO-SEEKER-0'.$number,'age'=>20,'gender'=>'prefer-not-to-say','account_created'=>now()]);
                    continue;
                }
                $model=match($role) {'adviser'=>Adviser::class,'helper'=>Helper::class,'moderator'=>Moderator::class};
                $data=['first_name'=>'Demo','last_name'=>ucfirst($role).' '.$number,'email'=>$account['email']];
                if($role==='helper') $data+=['adviser_id'=>$advisers[$number]->id,'availability'=>'unavailable','status'=>'unavailable','max_concurrent_sessions'=>1];
                $profile=$model::firstOrCreate(['user_account_id'=>$user->id],$data);
                if($role==='adviser') $advisers[$number]=$profile;
            }
        });
        $this->command?->info('Eight local demo users are ready. No Admin or Professional accounts were changed.');
    }
}
