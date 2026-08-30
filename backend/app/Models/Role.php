<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'super-admin';

    public const CENTRAL_ADMIN = 'central-admin';

    public const PROVINCE_ADMIN = 'province-admin';

    public const DISTRICT_ADMIN = 'district-admin';

    public const RECRUITMENT_MANAGER = 'recruitment-manager';

    public const CONTENT_MANAGER = 'content-manager';

    public const REPORT_MANAGER = 'report-manager';

    public const CADET = 'cadet';

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Get the permissions assigned to the role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Get the users assigned to the role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
