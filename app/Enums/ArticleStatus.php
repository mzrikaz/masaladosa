<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Pending = 'pending';
    case Rephrasing = 'rephrasing';
    case Published = 'published';
    case Failed = 'failed';
    case Unpublished = 'unpublished';
}
