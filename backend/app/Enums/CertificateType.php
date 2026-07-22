<?php

namespace App\Enums;

enum CertificateType: string
{
    case Completion = 'completion';
    case Skill = 'skill';
    case MentorshipHours = 'mentorship_hours';
}
