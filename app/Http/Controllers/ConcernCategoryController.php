<?php
namespace App\Http\Controllers;
use App\Models\ConcernCategory;
use App\Services\SupportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ConcernCategoryController extends Controller {
 private function authorizeManager():void {abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role,['adviser','admin'],true),403);}
 public function index(Request $request){$this->authorizeManager();$data=$request->validate(['status'=>'nullable|in:active,inactive']);$categories=ConcernCategory::query()->when($data['status']??null,fn($q,$status)=>$q->where('is_active',$status==='active'))->orderBy('concern_name')->paginate(15)->withQueryString();return view('concerns.index',compact('categories'));}
 public function save(Request $request,?ConcernCategory $category=null){
 $this->authorizeManager();$data=$request->validate(['concern_name'=>'required|string|max:100','description'=>'nullable|string|max:500','is_active'=>'required|boolean']);$data['concern_name']=trim($data['concern_name']);
 DB::transaction(function()use($category,$data){
 if(ConcernCategory::whereRaw('LOWER(concern_name) = ?',[mb_strtolower($data['concern_name'])])->when($category?->exists,fn($q)=>$q->where('id','!=',$category->id))->exists())throw ValidationException::withMessages(['concern_name'=>'This concern already exists. Edit or reactivate the existing category.']);
 if($category?->exists){$category=ConcernCategory::lockForUpdate()->findOrFail($category->id);if($category->sessions()->exists() && $category->concern_name!==$data['concern_name'])throw ValidationException::withMessages(['concern_name'=>'This category is used in session history. Keep its name and create a new category instead.']);$category->update($data);}else{$category=ConcernCategory::create($data);}
 SupportAudit::record('concern_category_saved',$category,['active'=>(bool)$category->is_active]);
 });return back()->with('success','Concern category saved.');}
}
