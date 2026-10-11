@extends('errors::layout')

@section('message', $message !== '' ? $message : $title)
