@props(['metrics','definitions'])
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
@foreach(['total'=>['Session records',''],'completed'=>['Completed sessions',''],'cancelled'=>['Cancelled / abandoned',''],'active'=>['Active sessions',''],'completion_rate'=>['Completion rate','%'],'referral_rate'=>['Referral rate','%'],'response_minutes'=>['Average response',' min'],'waiting_minutes'=>['Average waiting',' min'],'duration_minutes'=>['Average duration',' min'],'satisfaction'=>['Satisfaction',' / 5'],'referral_approval_rate'=>['Referral approval rate','%'],'duty_hours'=>['Scheduled duty',' h']] as $key=>$label)
<div class="bg-white border border-gray-200 rounded-2xl p-4" title="{{ $definitions[$key] ?? $definitions['period'] }}"><p class="text-xs text-gray-500">{{ $label[0] }}</p><p class="font-bold text-xl text-gray-800 mt-2">{{ $metrics[$key]===null ? 'No data' : $metrics[$key].$label[1] }}</p></div>
@endforeach
</div>
