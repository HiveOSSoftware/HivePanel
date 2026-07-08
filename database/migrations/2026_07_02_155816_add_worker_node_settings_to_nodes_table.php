<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');

            $table->string('public_fqdn')->nullable()->after('location');
            $table->string('internal_fqdn')->nullable()->after('public_fqdn');

            $table->unsignedInteger('daemon_port')->default(8443)->after('scheme');
            $table->unsignedInteger('sftp_port')->default(2022)->after('daemon_port');

            $table->boolean('behind_proxy')->default(false)->after('sftp_port');
            $table->boolean('maintenance_mode')->default(false)->after('behind_proxy');

            $table->decimal('cpu_threads', 8, 2)->nullable()->after('is_active');

            $table->unsignedInteger('memory_mib')->nullable()->after('cpu_threads');
            $table->unsignedInteger('memory_overallocate')->default(0)->after('memory_mib');

            $table->unsignedInteger('disk_mib')->nullable()->after('memory_overallocate');
            $table->unsignedInteger('disk_overallocate')->default(0)->after('disk_mib');

            $table->unsignedInteger('max_upload_mib')->default(100)->after('disk_overallocate');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'public_fqdn',
                'internal_fqdn',
                'daemon_port',
                'sftp_port',
                'behind_proxy',
                'maintenance_mode',
                'cpu_threads',
                'memory_mib',
                'memory_overallocate',
                'disk_mib',
                'disk_overallocate',
                'max_upload_mib',
            ]);
        });
    }
};