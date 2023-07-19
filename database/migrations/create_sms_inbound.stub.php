<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsInbound extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_inbound', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('gateway_id')->unsigned()->index()->nullable();
            $table->text('content');
            $table->bigInteger('recipient')->unsigned()->index();
            $table->bigInteger('sender')->unsigned()->index();
            $table->text('details');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_inbound');
    }
}
