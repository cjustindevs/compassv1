<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReferralAppointment extends Model {
    protected $guarded = ['id'];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime','meeting_details'=>'encrypted'];
    public function referral() { return $this->belongsTo(Referral::class); }
}
