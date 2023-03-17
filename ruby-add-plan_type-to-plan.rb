# Note June, 2017
#
# My contributions to the Ruby codebase were only when I needed to. 
# We have very talented Ruby developers that are very supportive of the
# front-end developers.  

class AddPlanTypeToPlan < ActiveRecord::Migration
  class Plan < ActiveRecord::Base
    enum plan_type: [:service_plan, :add_on, :shared_plan_access]
  end

  def up
    add_column :plans, :plan_type, :string, default: "service_plan", nil: false, index: true

    Plan.all.each do |plan|
      if plan[:add_on]
        plan.update!(plan_type: "add_on")
      else
        plan.update!(plan_type: "service_plan")
      end
    end

    remove_column :plans, :add_on
  end

  def down
    add_column :plans, :add_on, :boolean, default: false

    Plan.all.each do |plan|
      if plan.plan_type == "add_on"
        plan.update!(add_on: true)
      end
    end

    remove_column :plans, :plan_type
  end
end