<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('gateway_id')->unsigned()->index()->nullable();
            $table->text('content');
            $table->bigInteger('recipient')->unsigned()->index();
            $table->boolean('queued')->default(false)->index();
            $table->boolean('sent')->default(false)->index();
            $table->boolean('delivered')->default(false)->index();
            $table->boolean('failed')->default(false)->index();
            $table->boolean('replied')->default(false)->index();
            $table->text('deliveries');
            $table->text('replies');
            $table->text('details');
            $table->timestamps();
            $table->dateTime('delivered_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_messages');
    }
}
