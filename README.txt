First i create a middleware that will check user authentication and return me some amount of data related to user.

app/Http/Middleware/LoginAuth.php

after creating middlware integerate in to the BookingController i am calling LoginAuth in the constructor and create a new function storeBooking it will be use in midllware. This is same functionality but different stlye of code.

In the storeBooking function i am validate all fields through the laravel valdation methods because it will be great for us if i follow laravel structure. 

Now i am create 3 functions in BaseRepository file which is name below 

1) __validateRequestParams (this method check and return false if filed given wrong data)

2) __sendError (this method return validation messages)

3) __sendResponse (this method return sucess response)

you can check my code in BookingController,BaseRepository and BookingRepository and willExpireAt methode is fine i don't think so modify this function.

Thanks