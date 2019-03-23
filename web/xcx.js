const app = getApp()


Page({

  /**
   * 页面的初始数据
   */
  data: {
    t_id: 0,
    order_id: '',
    not_order_id: '',
    list: [],
    all_order_list: [],

  },

	  //付款
  fukuan(e) {
    const order_id = e.target.dataset.order_id
    //console.log(order_id)

    //后台查询订单信息
    app.http('c=order&a=detail', {
      id: order_id,
    })
    .then(res => {
      //console.log(res.data)
      if (res.errorCode == 200) {
              //code 用于获取openID的条件之一
              wx.request({
                url: 'https://www.xxxx.com/index.php',//后台地址
                method: "POST",
                data: {
                  c:'xxx',
                  a:'xxx',
                  openid: wx.getStorageSync('openid'),//用户openid
                  order_number: res.data.order_number,//订单号  看自己的业务流程
                  price: res.data.price,    //价格
                },
                header: {
                  'content-type': 'application/x-www-form-urlencoded' // 默认值
                },
                success: function (res) {  //后端返回的数据
                  var data = res.data;
                  //console.log(data);
                  //console.log(data["timeStamp"]);
                  wx.requestPayment({
                    timeStamp: data['timeStamp'],
                    nonceStr: data['nonceStr'],
                    package: data['package'],
                    signType: data['signType'],
                    paySign: data['paySign'],
                    success: function (res) {
                      wx.showToast({
                        title: '支付成功',
                        icon: 'success',
                        duration: 2000
                      })
                    },
                    fail: function (res) {
                      //console.log(res);
                    }
                  })
                }
              });
      }
      //console.log(this.data.not_order_id)
    });


  },

  
  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function(options) {
 
  
  },

  /**
   * 生命周期函数--监听页面初次渲染完成
   */
  onReady: function() {

  },

  /**
   * 生命周期函数--监听页面显示
   */
  onShow: function() {

  },

  /**
   * 生命周期函数--监听页面隐藏
   */
  onHide: function() {

  },

  /**
   * 生命周期函数--监听页面卸载
   */
  onUnload: function() {

  },

  /**
   * 页面相关事件处理函数--监听用户下拉动作
   */
  onPullDownRefresh: function() {

  },

  /**
   * 页面上拉触底事件的处理函数
   */
  onReachBottom: function() {

  }
 

})