<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculatedResult extends Model
{
    protected $table = 'calculated_results';

    protected $guarded = ['id'];

    protected $casts = ['passed' => 'boolean', 'percentage' => 'decimal:2', 'grade_points' => 'decimal:2', 'credits' => 'decimal:2', 'quality_points' => 'decimal:2', 'absent' => 'boolean'];

    public function batch()
    {
        return $this->belongsTo(ResultBatch::class, 'result_batch_id');
    }

    public function examCourse()
    {
        return $this->belongsTo(ExamCourse::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
