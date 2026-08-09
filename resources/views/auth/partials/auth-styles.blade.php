<style>
    body.loginpage {
        background: #c60813;
        font-family: 'Roboto', sans-serif, Helvetica, Arial;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .loginpanel {
        width: 100%;
        max-width: 350px;
        text-align: center;
        padding: 20px;
    }

    .logo img {
        max-width: 100%;
        height: auto;
        margin-bottom: 30px;
    }

    .inputwrapper {
        margin-bottom: 15px;
    }

    .inputwrapper input {
        width: 100%;
        padding: 12px;
        border: 0;
        background: #fff;
        box-sizing: border-box;
        outline: none;
        font-size: 14px;
    }

    .inputwrapper button {
        width: 100%;
        padding: 12px;
        background: #dd0915;
        border: 1px solid #a30c15;
        color: #fff;
        text-transform: uppercase;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
    }

    .inputwrapper button:hover {
        background: #a30c15;
    }

    .loginfooter {
        position: fixed;
        bottom: 20px;
        width: 100%;
        text-align: center;
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-family: Arial, sans-serif;
    }

    .alert-error {
        color: white;
        background: #ea4145;
        padding: 10px;
        font-size: 12px;
        margin-bottom: 20px;
        text-align: center;
    }

    .alert-notice {
        color: #fff;
        background: rgba(0, 0, 0, 0.25);
        padding: 10px;
        font-size: 12px;
        margin-bottom: 20px;
        text-align: center;
        line-height: 1.5;
    }
</style>
