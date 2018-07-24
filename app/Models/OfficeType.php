<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeType extends Model
{
	/**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'office_types';
    
    /**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name'
	];

	/**
     * Get the possible category associated with the office.
     */
    public function category()
    {
        return $this->hasOne(OfficeTypeCategory::class, 'office_type_id', 'id');
    }
}
