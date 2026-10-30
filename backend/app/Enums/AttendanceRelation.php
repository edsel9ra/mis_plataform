<?php

namespace App\Enums;

enum AttendanceRelation: string
{
    case Participant = 'participant';
    case Responsible = 'responsible';
    case Mentor = 'mentor';
    case Other = 'other';
}
