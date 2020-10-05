<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Eloquent;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * DetectionEvent
 *
 * @mixin Eloquent
 */
class DetectionEvent extends Model
{
    use HasRelationships;

    protected $dates = ['occurred_at'];

    protected $fillable = ['image_file_name', 'deepstack_response', 'image_dimensions', 'occurred_at'];

    public function detectionProfiles()
    {
        return $this->hasManyDeep('App\DetectionProfile', ['App\AiPrediction', 'ai_prediction_detection_profile'])
            ->withPivot('ai_prediction_detection_profile', ['is_masked']);
    }

    public function patternMatchedProfiles()
    {
        return $this->belongsToMany('App\DetectionProfile', 'pattern_match')->withPivot('is_profile_active');;
    }

    public function aiPredictions()
    {
        return $this->hasMany('App\AiPrediction');
    }

    public function getDeepstackResultAttribute()
    {
        return json_decode($this->deepstack_response);
    }
}
